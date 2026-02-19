#!/usr/bin/env python3
"""
DATA CLEANER - Version 3.0
===========================
Rôle : Nettoyage de base uniquement (sanitize)
- Suppression doublons / lignes-colonnes vides
- Détection et conversion des types (dates, prix, numériques)
- Correction des formats (DD/MM vs MM/DD, symboles monétaires)
- Valeurs négatives impossibles → valeur absolue
NE PAS : imputer, enrichir, valider règles métier (→ cross_reference.py)
"""
import os
import sys
import json
import csv
import pandas as pd
import numpy as np
import warnings
warnings.filterwarnings('ignore')

# ============================================================================
# GROQ LLM CLIENT (pour détection de types uniquement)
# ============================================================================

class GroqLLM:
    """Client Groq pour détection intelligente des types de colonnes"""
    
    def __init__(self):
        self.available = False
        self.model = None
        
        try:
            from groq import Groq
            
            api_key = os.environ.get("GROQ_API_KEY")
            if not api_key:
                print("⚠️  GROQ_API_KEY not defined (type detection without LLM)", file=sys.stderr)
                return
            
            self.client = Groq(api_key=api_key)
            
            try:
                self.client.chat.completions.create(
                    model="llama-3.3-70b-versatile",
                    messages=[{"role": "user", "content": "test"}],
                    max_tokens=5
                )
                self.model = "llama-3.3-70b-versatile"
                self.available = True
                print("✅ Groq LLM initialized (Llama 3.3 70B)", file=sys.stderr)
            except:
                try:
                    self.client.chat.completions.create(
                        model="llama-3.1-8b-instant",
                        messages=[{"role": "user", "content": "test"}],
                        max_tokens=5
                    )
                    self.model = "llama-3.1-8b-instant"
                    self.available = True
                    print("✅ Groq LLM initialized (Llama 3.1 8B)", file=sys.stderr)
                except Exception as e:
                    print(f"⚠️  Groq LLM not available: {e}", file=sys.stderr)
        
        except ImportError:
            print("⚠️  Package 'groq' not installed (pip install groq)", file=sys.stderr)
    
    def detect_column_types(self, profile):
        """Détecte les types de colonnes (date, numérique, catégoriel, etc.)"""
        
        if not self.available:
            return None
        
        prompt = f"""Analyze this dataset profile and classify each column type.

Dataset profile:
{json.dumps(profile, indent=2)}

For EACH column, respond with a JSON object containing:
- is_date: boolean (is this a date/time column?)
- can_be_negative: boolean (can this column have negative values? only for numeric)
- reason: brief explanation

IMPORTANT RULES:
1. Date columns: is_date=true
2. Negative values:
   - can_be_negative=false for: prices, costs, ages, distances, quantities (physical counts)
   - can_be_negative=true for: temperatures, balances, profits, returns/refunds

Respond with ONLY valid JSON (no markdown, no explanations):
{{
  "column_name": {{
    "is_date": true/false,
    "can_be_negative": true/false,
    "reason": "..."
  }}
}}"""

        try:
            print("🤖 Groq LLM consultation for type detection...", file=sys.stderr)
            
            response = self.client.chat.completions.create(
                model=self.model,
                messages=[{"role": "user", "content": prompt}],
                temperature=0,
                max_tokens=1500,
                response_format={"type": "json_object"}
            )
            
            result = json.loads(response.choices[0].message.content)
            print(f"✅ Types detected for {len(result)} columns", file=sys.stderr)
            return result
            
        except Exception as e:
            print(f"⚠️  LLM error: {e}", file=sys.stderr)
            return None

# ============================================================================
# HELPER FUNCTIONS
# ============================================================================

def is_date_column(series):
    """Détecte si une colonne contient des dates"""
    date_keywords = ['date', 'day', 'time', 'birthday', 'timestamp', 'created', 'updated', 'birth']
    col_name_lower = series.name.lower() if series.name else ''
    
    if any(keyword in col_name_lower for keyword in date_keywords):
        return True
    
    if series.dtype == 'object':
        sample = series.dropna().head(100).astype(str)
        if len(sample) == 0:
            return False
        date_pattern_count = sample.str.contains(r'\d{1,4}[/-]\d{1,2}[/-]\d{1,4}', regex=True).sum()
        if date_pattern_count > len(sample) * 0.5:
            return True
    
    return False

def create_dataset_profile(df):
    """Crée un profil compact du dataset pour le LLM"""
    profile = {
        "total_rows": len(df),
        "total_columns": len(df.columns),
        "columns": {}
    }
    
    for col in df.columns:
        col_info = {
            "dtype": str(df[col].dtype),
            "null_count": int(df[col].isna().sum()),
            "null_percentage": float(df[col].isna().sum() / len(df) * 100) if len(df) > 0 else 0,
            "unique_count": int(df[col].nunique())
        }
        
        if pd.api.types.is_numeric_dtype(df[col]):
            non_null = df[col].dropna()
            if len(non_null) > 0:
                col_info.update({
                    "min": float(non_null.min()),
                    "max": float(non_null.max()),
                    "mean": float(non_null.mean()),
                    "negative_count": int((non_null < 0).sum()),
                })
        
        col_info["sample_values"] = df[col].dropna().head(5).tolist()
        profile["columns"][col] = col_info
    
    return profile

def create_fallback_type_decisions(df):
    """Détection de types de base si LLM non disponible"""
    decisions = {}
    
    for col in df.columns:
        is_date = is_date_column(df[col])
        decisions[col] = {
            "is_date": is_date,
            "can_be_negative": True,
            "reason": "Fallback rule (LLM unavailable)"
        }
    
    return decisions

# ============================================================================
# CLEANING FUNCTIONS
# ============================================================================

def clean_basic(df, row_thresh=0.5, col_thresh=0.3):
    """Nettoyage de base : doublons, lignes/colonnes vides"""
    
    print("\n📋 Basic cleaning...", file=sys.stderr)
    
    # Supprimer colonnes/lignes complètement vides
    df = df.dropna(axis=0, how='all').dropna(axis=1, how='all')
    
    # Supprimer doublons
    before = len(df)
    df = df.drop_duplicates()
    if len(df) < before:
        print(f"   🗑️  {before - len(df)} duplicates removed", file=sys.stderr)
    
    # Supprimer lignes avec trop de NULL
    before = len(df)
    df = df.dropna(axis=0, thresh=int(len(df.columns) * row_thresh))
    if len(df) < before:
        print(f"   🗑️  {before - len(df)} rows removed (<50% data)", file=sys.stderr)
    
    # Supprimer colonnes avec trop de NULL
    before_cols = len(df.columns)
    df = df.dropna(axis=1, thresh=int(len(df) * col_thresh))
    if len(df.columns) < before_cols:
        print(f"   🗑️  {before_cols - len(df.columns)} columns removed (>70% NULL)", file=sys.stderr)
    
    return df

def clean_dates(df, decisions):
    """
    Convertit les colonnes dates avec détection automatique du format.
    Les NULL restants sont laissés tels quels (pas d'imputation).
    """
    date_columns = {}
    
    for col, decision in decisions.items():
        if col not in df.columns:
            continue
        
        if decision.get('is_date'):
            print(f"   📅 Date: {col}", file=sys.stderr)
            
            sample = df[col].dropna().head(100)
            if len(sample) == 0:
                print(f"      ⚠️  Empty column, ignored", file=sys.stderr)
                continue
            
            formats_to_test = [
                ('%Y-%m-%d', 'YYYY-MM-DD'),
                ('%d/%m/%Y', 'DD/MM/YYYY'),
                ('%m/%d/%Y', 'MM/DD/YYYY'),
                ('%Y/%m/%d', 'YYYY/MM/DD'),
                ('%d-%m-%Y', 'DD-MM-YYYY'),
                (None, 'Auto (pandas)'),
            ]
            
            best_format = None
            best_success_rate = 0
            best_name = 'Auto'
            
            for fmt, name in formats_to_test:
                try:
                    if fmt is None:
                        test_result = pd.to_datetime(sample, errors='coerce')
                    else:
                        test_result = pd.to_datetime(sample, format=fmt, errors='coerce')
                    success_rate = test_result.notna().sum() / len(sample)
                except:
                    success_rate = 0
                
                if success_rate > best_success_rate:
                    best_success_rate = success_rate
                    best_format = fmt
                    best_name = name
            
            print(f"      Selected format: {best_name} ({best_success_rate*100:.1f}% succes)", file=sys.stderr)
            
            if best_format is None:
                df[col] = pd.to_datetime(df[col], errors='coerce')
            else:
                df[col] = pd.to_datetime(df[col], format=best_format, errors='coerce')
            
            failed = df[col].isna().sum()
            if failed > 0:
                print(f"      ⚠️ {failed} invalid dates → left NULL (imputation in cross_reference)", file=sys.stderr)
            
            if df[col].notna().sum() > 0:
                date_columns[col] = df[col].copy()
                df = df.drop(columns=[col])
            else:
                print(f"      ❌ Total failure, column ignored", file=sys.stderr)
    
    return df, date_columns

def clean_prices(df):
    """
    Conversion robuste des colonnes monétaires.
    25,50 → 25.50 / 1 200€ → 1200 / $30.00 → 30.00
    """
    price_keywords = [
        'price', 'cost', 'amount', 'value',
        'salary', 'revenue', 'total',
        'usd', 'eur', 'gbp', 'fee', 'charge'
    ]

    for col in df.columns:
        col_lower = col.lower()

        if any(keyword in col_lower for keyword in price_keywords):
            print(f"   💵 Price detected: {col}", file=sys.stderr)

            df[col] = (
                df[col]
                .astype(str)
                .str.replace(r'[€$£¥₹]', '', regex=True)
                .str.replace(r'\s', '', regex=True)
                .str.replace(',', '.', regex=False)
            )
            df[col] = pd.to_numeric(df[col], errors='coerce')

    return df

def handle_negatives(df, decisions):
    """
    Corrige les valeurs négatives incorrectes → valeur absolue.
    Ne remplace PAS par NULL.
    """
    always_positive_keywords = [
        'salary', 'wage', 'income', 'revenue', 'earnings',
        'price', 'cost', 'amount', 'fee', 'charge',
        'age', 'years', 'old',
        'distance', 'length', 'width', 'height', 'weight',
        'count', 'quantity', 'qty', 'number', 'total'
    ]
    
    for col, decision in decisions.items():
        if col not in df.columns:
            continue
        
        if not pd.api.types.is_numeric_dtype(df[col]):
            continue
        
        llm_says_no_negative = decision.get('can_be_negative') is False
        col_lower = col.lower()
        forced_positive = any(keyword in col_lower for keyword in always_positive_keywords)
        
        if llm_says_no_negative or forced_positive:
            neg_count = (df[col] < 0).sum()
            if neg_count > 0:
                reason = "LLM" if llm_says_no_negative else "forced rule"
                print(f"   ⚠️  {col}: {neg_count} negatives → absolute value ({reason})", file=sys.stderr)
                df.loc[df[col] < 0, col] = df.loc[df[col] < 0, col].abs()
    
    return df

# ============================================================================
# MAIN CLEANER CLASS
# ============================================================================

class DataCleaner:
    """
    Data cleaner - sanitize only.
    Imputation, enrichment and business validation
    are delegated to cross_reference.py
    """
    
    def __init__(self, file_path, use_llm=True, row_threshold=0.5, col_threshold=0.3):
        self.file_path = file_path
        self.use_llm = use_llm
        self.row_threshold = row_threshold
        self.col_threshold = col_threshold
        self.df = self._load_file(file_path)
    
    def _load_file(self, path):
        """Charge un fichier (CSV, Excel, JSON, XML)"""
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
                encodings = ['utf-8', 'latin-1', 'iso-8859-1', 'cp1252', 'utf-16']
                
                for encoding in encodings:
                    try:
                        df = pd.read_csv(path, sep=None, engine='python',
                                       encoding=encoding, on_bad_lines='skip')
                        print(f"✅ Chargé avec encoding: {encoding}", file=sys.stderr)
                        return df
                    except UnicodeDecodeError:
                        continue
                    except Exception:
                        try:
                            df = pd.read_csv(path, encoding=encoding, on_bad_lines='skip')
                            print(f"✅ Load with encoding: {encoding}", file=sys.stderr)
                            return df
                        except:
                            continue
                
                raise ValueError("Impossible de lire le fichier avec tous les encodings testés")
            
            else:
                raise ValueError(f"Format non supporté: {ext}")
        
        except Exception as e:
            raise ValueError(f"Erreur chargement: {str(e)}")
    
    def clean(self):
        """
        Pipeline de nettoyage (sanitize uniquement) :
        1. Nettoyage de base (doublons, vides)
        2. Détection des types via LLM
        3. Conversion des dates
        4. Conversion des prix
        5. Correction des négatifs
        
        ❌ PAS d'imputation des NULL
        ❌ PAS de recalcul de colonnes dérivées
        ❌ PAS de validation des règles métier
        """
        
        print(f"\n{'='*60}", file=sys.stderr)
        print(f"📂 {os.path.basename(self.file_path)}", file=sys.stderr)
        print(f"{'='*60}", file=sys.stderr)
        print(f"Rows: {len(self.df)}, Columns: {len(self.df.columns)}", file=sys.stderr)
        
        # 1. Nettoyage de base
        self.df = clean_basic(self.df, self.row_threshold, self.col_threshold)
        
        # 2. Créer profil pour LLM
        print("\n📊 Analysis of column types...", file=sys.stderr)
        profile = create_dataset_profile(self.df)
        
        # 3. Détecter les types via LLM
        if self.use_llm:
            llm = GroqLLM()
            decisions = llm.detect_column_types(profile)
            if not decisions:
                print("📋 Using fallback rules...", file=sys.stderr)
                decisions = create_fallback_type_decisions(self.df)
        else:
            decisions = create_fallback_type_decisions(self.df)
        
        # 4. Convertir les dates
        print(f"\n🔧 Type conversion...", file=sys.stderr)
        self.df, date_columns = clean_dates(self.df, decisions)
        
        # 5. Convertir les prix
        self.df = clean_prices(self.df)
        
        # 6. Corriger les négatifs
        self.df = handle_negatives(self.df, decisions)
        
        # 7. Réintégrer les dates (format ISO)
        for col, date_series in date_columns.items():
            self.df[col] = date_series.dt.strftime('%Y-%m-%d')
            print(f"   ✅ Date re-integrated: {col}", file=sys.stderr)
        
        # Résumé des NULL restants (pour info, pas d'action)
        null_summary = self.df.isna().sum()
        null_cols = null_summary[null_summary > 0]
        if len(null_cols) > 0:
            print(f"\n📊 Remaining NULL values (to be processed in cross_reference.py):", file=sys.stderr)
            for col, count in null_cols.items():
                pct = count / len(self.df) * 100
                print(f"   • {col}: {count} ({pct:.1f}%)", file=sys.stderr)
        
        print(f"\n✅ Sanitize finished: {len(self.df)} lines, {len(self.df.columns)} columns", file=sys.stderr)
        
        return self.df

# ============================================================================
# CLI INTERFACE
# ============================================================================

def main():
    if len(sys.argv) >= 3:
        input_file = sys.argv[1]
        output_file = sys.argv[2]
        
        use_llm = True
        if len(sys.argv) >= 4:
            try:
                options = json.loads(sys.argv[3])
                use_llm = options.get('use_llm', True)
            except:
                pass
        
        try:
            cleaner = DataCleaner(input_file, use_llm=use_llm)
            df_clean = cleaner.clean()
            
            df_clean.to_csv(output_file, index=False, quoting=csv.QUOTE_MINIMAL)
            
            result = {
                'status': 'success',
                'input_file': input_file,
                'output_file': output_file,
                'rows': len(df_clean),
                'columns': len(df_clean.columns),
                'null_remaining': int(df_clean.isna().sum().sum()),
                'message': f'Sanitize terminé: {os.path.basename(input_file)}'
            }
            print(json.dumps(result))
            
        except Exception as e:
            result = {
                'status': 'error',
                'message': str(e)
            }
            print(json.dumps(result))
            sys.exit(1)
    
    else:
        
        sys.exit(1)

if __name__ == "__main__":
    main()