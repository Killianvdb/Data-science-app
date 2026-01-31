import os
import sys
import json
import re
import pandas as pd
import numpy as np
from sklearn.base import TransformerMixin
from sklearn.ensemble import RandomForestRegressor
# This line is essential to enable the IterativeImputer
from sklearn.experimental import enable_iterative_imputer  
from sklearn.impute import SimpleImputer, KNNImputer, IterativeImputer
import warnings
warnings.filterwarnings('ignore')

class prepross(TransformerMixin):
    def __init__(self, **kwargs):
        super().__init__(**kwargs)

    def fit(self, X, y=None):
        # Fills categorical with mode, numerical with mean
        self.fill = {}
        for c in X.columns:
            try:
                # Try to convert to numeric first
                numeric_col = pd.to_numeric(X[c], errors='coerce')
                if numeric_col.notna().sum() > 0:
                    # If we have any valid numbers, treat as numeric
                    self.fill[c] = numeric_col.mean()
                else:
                    # Otherwise treat as categorical
                    if not X[c].empty and X[c].notna().any():
                        self.fill[c] = X[c].value_counts().index[0]
                    else:
                        self.fill[c] = None
            except:
                # Fallback: use mode for any problematic columns
                if not X[c].empty and X[c].notna().any():
                    self.fill[c] = X[c].value_counts().index[0]
                else:
                    self.fill[c] = None
        return self

    def transform(self, X, y=None):
        return X.fillna(self.fill)

def rm_rows_cols(df, row_thresh=0.8, col_thresh=0.8):
    if "Index" in df.columns:
        df = df.drop("Index", axis=1)
    df.columns = [col.strip() for col in df.columns]
    df = df.drop_duplicates()
    
    # Drop completely empty rows/cols first
    df = df.dropna(axis=0, how='all').dropna(axis=1, how='all')
    
    # Threshold based dropping
    df = df.dropna(axis=0, thresh=int(len(df.columns) * row_thresh))
    df = df.dropna(axis=1, thresh=int(len(df) * col_thresh))
    return df.infer_objects()

def replace_special_character(df, usr_char=None, do=None, ignore_col=None):
    spec_chars = ["!", '"', "#", "%", "&", "'", "(", ")", "*", "+", ",", "-", ".", "/", ":", ";", 
                  "<", "=", ">", "?", "@", "[", "\\", "]", "^", "_", "`", "{", "|", "}", "~", 
                  "–", "//", "%*", ":/", ".;", "Ø", "§", '$', "£"]
    
    if ignore_col is None: ignore_col = []
    if usr_char is None: usr_char = []

    if do == 'remove':
        spec_chars = [c for c in spec_chars if c not in usr_char]
    elif do == 'add':
        spec_chars.extend(usr_char)

    cols_to_fix = [c for c in df.columns if c not in ignore_col]
    pattern = "[" + re.escape("".join(spec_chars)) + "]"
    
    # Only apply to string columns to avoid errors
    for col in cols_to_fix:
        if df[col].dtype == 'object' or df[col].dtype == 'string':
            df[col] = df[col].astype(str).replace(pattern, '', regex=True)
    return df

def custom_imputation(df, imputation_type="RDF"):
    # Make a copy to avoid warnings
    df = df.copy()
    
    # Convert columns intelligently
    for col in df.columns:
        try:
            # Try to convert to numeric
            numeric_version = pd.to_numeric(df[col], errors='coerce')
            if numeric_version.notna().sum() > len(df) * 0.5:  # If more than 50% are valid numbers
                df[col] = numeric_version
        except:
            pass
    
    # Now identify column types
    numeric_columns = df.select_dtypes(include=[np.number]).columns.tolist()
    categorical_columns = [col for col in df.columns if col not in numeric_columns]

    data_numeric = df[numeric_columns].copy() if numeric_columns else pd.DataFrame()
    data_categorical = df[categorical_columns].copy() if categorical_columns else pd.DataFrame()

    # Handle Categorical
    if not data_categorical.empty:
        try:
            data_categorical = pd.DataFrame(
                prepross().fit_transform(data_categorical), 
                columns=data_categorical.columns,
                index=data_categorical.index
            )
        except Exception as e:
            print(f"Warning: Categorical imputation issue: {e}", file=sys.stderr)
    
    if data_numeric.empty:
        return data_categorical if not data_categorical.empty else df

    # Handle Numerical with Modern Imputers
    try:
        if imputation_type == "KNN":
            imp = KNNImputer(n_neighbors=5)
        elif imputation_type == "RDF":
            imp = IterativeImputer(
                estimator=RandomForestRegressor(n_estimators=100, n_jobs=-1),
                max_iter=10, 
                random_state=42
            )
        else:  # mean, median, most_frequent
            imp = SimpleImputer(strategy=imputation_type)

        data_numeric_final = pd.DataFrame(
            imp.fit_transform(data_numeric), 
            columns=data_numeric.columns,
            index=data_numeric.index
        )
    except Exception as e:
        print(f"Warning: Numeric imputation issue: {e}, falling back to mean", file=sys.stderr)
        data_numeric_final = data_numeric.fillna(data_numeric.mean())
    
    # Combine results
    if not data_categorical.empty:
        return pd.concat([data_numeric_final, data_categorical], axis=1)
    else:
        return data_numeric_final

class DataCleaning():
    def __init__(self, file_path, separator=",", row_threshold=0.8, col_threshold=0.8, 
                 special_character=None, action=None, ignore_columns=None, imputation_type="RDF"):
        self.file_path = file_path
        self.df = self._load_file(file_path, separator)
        self.row_threshold = row_threshold
        self.col_threshold = col_threshold
        self.special_character = special_character
        self.action = action
        self.ignore_columns = ignore_columns if ignore_columns else []
        self.imputation_type = imputation_type

    def _load_file(self, path, sep):
        ext = os.path.splitext(path)[-1].lower()
        try:
            if ext in ['.xlsx', '.xls']:
                sheets = pd.read_excel(path, sheet_name=None)
                return pd.concat(sheets.values(), ignore_index=True)
            elif ext == '.json':
                with open(path, 'r') as f:
                    return pd.json_normalize(json.load(f))
            elif ext == '.xml':
                return pd.read_xml(path)
            elif ext in ['.txt', '.csv']:
                # Try to auto-detect separator
                try:
                    return pd.read_csv(path, sep=None, engine='python', on_bad_lines='skip')
                except:
                    return pd.read_csv(path, sep=sep, on_bad_lines='skip')
            else:
                raise ValueError(f"Unsupported format: {ext}")
        except Exception as e:
            raise ValueError(f"Error loading file: {str(e)}")

    def start_cleaning(self):
        try:
            df = rm_rows_cols(self.df, self.row_threshold, self.col_threshold)
            df = replace_special_character(df, self.special_character, self.action, self.ignore_columns)
            df = custom_imputation(df, self.imputation_type)
            return df
        except Exception as e:
            raise Exception(f"Cleaning failed: {str(e)}")

# --- Process single file (for Laravel integration) ---
def process_single_file(input_file, output_file, options=None):
    """
    Process a single file with optional cleaning parameters
    
    Args:
        input_file: Path to input file
        output_file: Path to save cleaned CSV
        options: Dict with optional parameters
    """
    if options is None:
        options = {}
    
    try:
        cleaner = DataCleaning(
            input_file,
            row_threshold=options.get('row_threshold', 0.8),
            col_threshold=options.get('col_threshold', 0.8),
            special_character=options.get('special_character'),
            action=options.get('action'),
            ignore_columns=options.get('ignore_columns'),
            imputation_type=options.get('imputation_type', 'RDF')
        )
        clean_df = cleaner.start_cleaning()
        clean_df.to_csv(output_file, index=False)
        
        result = {
            'status': 'success',
            'input_file': input_file,
            'output_file': output_file,
            'rows': len(clean_df),
            'columns': len(clean_df.columns),
            'message': f'Successfully cleaned {os.path.basename(input_file)}'
        }
        print(json.dumps(result))
        return result
        
    except Exception as e:
        error_result = {
            'status': 'error',
            'message': str(e)
        }
        print(json.dumps(error_result))
        sys.exit(1)

# --- Batch Processing Function ---
def process_all_files(input_folder, output_folder):
    if not os.path.exists(output_folder):
        os.makedirs(output_folder)

    files = [f for f in os.listdir(input_folder) if os.path.isfile(os.path.join(input_folder, f))]
    results = []
    
    for file in files:
        full_path = os.path.join(input_folder, file)
        try:
            cleaner = DataCleaning(full_path)
            clean_df = cleaner.start_cleaning()
            
            name_part, ext_part = os.path.splitext(file)
            ext_label = ext_part.replace('.', '')
            output_filename = f"{name_part}_{ext_label}_CLEANED.csv"
            
            output_path = os.path.join(output_folder, output_filename)
            clean_df.to_csv(output_path, index=False)
            print(f"✅ Saved to: {output_path}")
            
            results.append({
                'file': file,
                'status': 'success',
                'output': output_filename
            })
            
        except Exception as e:
            print(f"❌ Failed to process {file}: {e}")
            results.append({
                'file': file,
                'status': 'error',
                'message': str(e)
            })
    
    return results

if __name__ == "__main__":
    # Check if running as CLI with arguments (for Laravel)
    if len(sys.argv) >= 3:
        input_file = sys.argv[1]
        output_file = sys.argv[2]
        
        # Optional: Parse JSON options if provided
        options = {}
        if len(sys.argv) >= 4:
            try:
                options = json.loads(sys.argv[3])
            except:
                pass
        
        process_single_file(input_file, output_file, options)
    
    # Otherwise run batch mode
    else:
        input_dir = "data_mission"
        output_dir = "cleaned_output"
        
        if os.path.exists(input_dir):
            results = process_all_files(input_dir, output_dir)
            print(f"\n🎉 Processed {len(results)} files! Check the 'cleaned_output' folder.")
        else:
            print(f"Folder '{input_dir}' not found. Please create it and add your files!")