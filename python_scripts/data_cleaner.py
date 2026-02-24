#!/usr/bin/env python3
"""
DATA CLEANER - Version 4.1
===========================
Rôle : Nettoyage de base uniquement (sanitize)
- Suppression doublons / lignes-colonnes vides
- Détection et conversion des types via LLM Gemini (dates, prix, numériques)
- Correction des formats (DD/MM vs MM/DD, symboles monétaires, mois abrégés)
- Valeurs négatives impossibles → valeur absolue ou NULL selon le contexte
NE PAS : imputer, enrichir, valider règles métier (→ cross_reference.py)

v4.1 changes vs v4.0:
  - GeminiLLM + _safe_stderr imported from llm_client.py (no more duplication)
  - format_dates(), format_prices(), detect_column_types() now proper LLM methods
  - Removed dead code (unused date-response flattener)
  - Removed duplicate '# GEMINI LLM CLIENT' comment block
"""

import os
import sys
import json
import csv
import re
import pandas as pd
import numpy as np
import warnings
warnings.filterwarnings('ignore')

# ── Shared LLM client (single source of truth) ────────────────────────────────
from llm_client import GeminiLLM, _safe_stderr


# ============================================================================
# HELPER FUNCTIONS
# ============================================================================

def is_date_column_heuristic(series: pd.Series) -> bool:
    """Détection heuristique basique (fallback si LLM indisponible)."""

    if pd.api.types.is_numeric_dtype(series):
        return False

    if series.dtype not in ['object'] and str(series.dtype) != 'str':
        return False

    col_name_lower = series.name.lower() if series.name else ''

    # Correspondance exacte sur le nom de colonne
    if any(
        kw == col_name_lower
        or col_name_lower.endswith('_' + kw)
        or col_name_lower.startswith(kw + '_')
        or ('_' + kw + '_') in col_name_lower
        for kw in ['date', 'timestamp', 'birthday']
    ):
        sample = series.dropna().head(20).astype(str)
        if sample.str.contains(r'\d{1,4}[/\-]\d{1,2}[/\-]\d{2,4}', regex=True).any():
            return True
        if sample.str.contains(
            r'\b(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)\b',
            case=False, regex=True
        ).any():
            return True
        return False

    # Détecter uniquement par le contenu des valeurs
    if series.dtype == 'object' or str(series.dtype) == 'str':
        sample = series.dropna().head(100).astype(str)
        if len(sample) == 0:
            return False

        numeric_ratio = pd.to_numeric(sample, errors='coerce').notna().sum() / len(sample)
        if numeric_ratio > 0.5:
            return False

        date_pattern_count = sample.str.contains(
            r'\d{1,4}[/\-]\d{1,2}[/\-]\d{2,4}', regex=True
        ).sum()
        abbr_month_count = sample.str.contains(
            r'\b(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)\b',
            case=False, regex=True
        ).sum()
        if date_pattern_count > len(sample) * 0.4 or abbr_month_count > len(sample) * 0.4:
            return True

    return False


def create_dataset_profile(df: pd.DataFrame) -> dict:
    """Crée un profil compact du dataset pour le LLM."""
    profile = {
        "total_rows": len(df),
        "total_columns": len(df.columns),
        "columns": {}
    }

    for col in df.columns:
        col_info = {
            "dtype": str(df[col].dtype),
            "null_count": int(df[col].isna().sum()),
            "null_percentage": round(
                float(df[col].isna().sum() / len(df) * 100), 1
            ) if len(df) > 0 else 0,
            "unique_count": int(df[col].nunique()),
            "sample_values": [str(v) for v in df[col].dropna().head(8).tolist()]
        }

        if pd.api.types.is_numeric_dtype(df[col]):
            non_null = df[col].dropna()
            if len(non_null) > 0:
                col_info.update({
                    "min": float(non_null.min()),
                    "max": float(non_null.max()),
                    "mean": round(float(non_null.mean()), 4),
                    "negative_count": int((non_null < 0).sum()),
                })

        profile["columns"][col] = col_info

    return profile


def create_fallback_type_decisions(df: pd.DataFrame) -> dict:
    """Détection de types basique si LLM non disponible."""
    decisions = {}
    for col in df.columns:
        decisions[col] = {
            "is_date": is_date_column_heuristic(df[col]),
            "can_be_negative": True,
            "reason": "Fallback heuristic (LLM unavailable)"
        }
    return decisions


# ============================================================================
# MOIS ÉTRANGERS — normalisation avant parsing
# ============================================================================

_FOREIGN_MONTHS = {
    'janvier': 'January', 'février': 'February', 'fevrier': 'February',
    'mars': 'March', 'avril': 'April', 'mai': 'May', 'juin': 'June',
    'juillet': 'July', 'août': 'August', 'aout': 'August',
    'septembre': 'September', 'octobre': 'October', 'novembre': 'November',
    'décembre': 'December', 'decembre': 'December',
    'jan': 'Jan', 'fév': 'Feb', 'fev': 'Feb', 'avr': 'Apr',
    'juil': 'Jul', 'aoû': 'Aug', 'aou': 'Aug',
    'sep': 'Sep', 'oct': 'Oct', 'nov': 'Nov', 'déc': 'Dec', 'dec': 'Dec',
    # Spanish
    'enero': 'January', 'febrero': 'February', 'marzo': 'March',
    'abril': 'April', 'mayo': 'May', 'junio': 'June', 'julio': 'July',
    'agosto': 'August', 'septiembre': 'September', 'octubre': 'October',
    'noviembre': 'November', 'diciembre': 'December',
    # Portuguese
    'janeiro': 'January', 'fevereiro': 'February', 'marco': 'March',
    'junho': 'June', 'julho': 'July', 'setembro': 'September',
    'outubro': 'October', 'novembro': 'November', 'dezembro': 'December',
    # Italian
    'gennaio': 'January', 'febbraio': 'February', 'aprile': 'April',
    'maggio': 'May', 'giugno': 'June', 'luglio': 'July',
    'settembre': 'September', 'ottobre': 'October', 'dicembre': 'December',
}

_FOREIGN_RE = re.compile(
    r'\b(' + '|'.join(
        re.escape(k) for k in sorted(_FOREIGN_MONTHS, key=len, reverse=True)
    ) + r')\b',
    flags=re.IGNORECASE
)


# ============================================================================
# CLEANING FUNCTIONS
# ============================================================================

def clean_basic(df: pd.DataFrame, row_thresh: float = 0.5,
                col_thresh: float = 0.3) -> pd.DataFrame:
    """Nettoyage de base : doublons, lignes/colonnes vides."""
    _safe_stderr("\n📋 Basic cleaning...")

    df = df.dropna(axis=0, how='all').dropna(axis=1, how='all')

    before = len(df)
    df = df.drop_duplicates()
    if len(df) < before:
        _safe_stderr(f"   🗑️  {before - len(df)} duplicates removed")

    before = len(df)
    df = df.dropna(axis=0, thresh=max(1, int(len(df.columns) * row_thresh)))
    if len(df) < before:
        _safe_stderr(f"   🗑️  {before - len(df)} rows removed (<{int(row_thresh*100)}% data)")

    before_cols = len(df.columns)
    df = df.dropna(axis=1, thresh=max(1, int(len(df) * col_thresh)))
    if len(df.columns) < before_cols:
        _safe_stderr(
            f"   🗑️  {before_cols - len(df.columns)} columns removed "
            f"(>{int((1-col_thresh)*100)}% NULL)"
        )

    return df


# ── Date parsing ──────────────────────────────────────────────────────────────

FALLBACK_DATE_FORMATS = [
    '%Y-%m-%d', '%d/%m/%Y', '%m/%d/%Y', '%Y/%m/%d',
    '%d-%m-%Y', '%m-%d-%Y',
    '%d-%b-%Y', '%d/%b/%Y', '%b-%d-%Y', '%b/%d/%Y',
    '%d-%B-%Y', '%d/%B/%Y', '%B-%d-%Y', '%B %d %Y',
    '%d %b %Y', '%d %B %Y',
    '%b %d, %Y', '%B %d, %Y',
    '%Y%m%d',
]


def _pandas_fallback_parse(series: pd.Series) -> pd.Series:
    """
    Tente de parser les dates restantes avec pandas multi-format.
    Normalise les mois non-anglais AVANT le parsing.
    """
    normalized = series.copy()
    mask_not_null = series.notna()
    if mask_not_null.any():
        normalized[mask_not_null] = series[mask_not_null].astype(str).apply(
            lambda v: _FOREIGN_RE.sub(
                lambda m: _FOREIGN_MONTHS.get(m.group(0).lower(), m.group(0)), v
            )
        )

    parsed = pd.Series(pd.NaT, index=series.index)

    for fmt in FALLBACK_DATE_FORMATS:
        still_null = parsed.isna() & normalized.notna()
        if not still_null.any():
            break
        chunk = pd.to_datetime(normalized[still_null], format=fmt, errors='coerce')
        parsed[still_null] = chunk

    # Last resort: pandas infer
    still_null = parsed.isna() & normalized.notna()
    if still_null.any():
        chunk = pd.to_datetime(normalized[still_null], errors='coerce')
        parsed[still_null] = chunk

    return parsed


def clean_dates(df: pd.DataFrame, decisions: dict,
                llm: GeminiLLM) -> tuple[pd.DataFrame, dict]:
    """
    Conversion des colonnes de dates en YYYY-MM-DD.
    Stratégie :
      1. LLM Gemini  → mapping {original → YYYY-MM-DD}
      2. Fallback pandas multi-format pour les valeurs non mappées
    """
    date_columns = {}

    for col, decision in decisions.items():
        if col not in df.columns or not decision.get('is_date'):
            continue

        _safe_stderr(f"   📅 Date processing: {col}")

        if df[col].dropna().empty:
            continue

        raw_series = df[col].astype(str).where(df[col].notna())
        raw_values = raw_series.dropna().tolist()

        # Step 1: LLM mapping
        llm_mapping = llm.format_dates(col, raw_values) if llm.available else None

        new_dates = pd.Series(np.nan, index=df.index, dtype='object')

        if llm_mapping:
            mapped = raw_series.map(
                lambda v: llm_mapping.get(str(v)) if pd.notna(v) else np.nan
            )
            valid_mask = mapped.notna() & (mapped != 'null') & (mapped != '')
            new_dates[valid_mask] = mapped[valid_mask]

            # Step 2: pandas fallback for unmapped values
            fallback_needed = df[col].notna() & ~valid_mask
            if fallback_needed.any():
                _safe_stderr(
                    f"   🔄 {col}: pandas fallback for "
                    f"{fallback_needed.sum()} unmapped values..."
                )
                fallback_parsed = _pandas_fallback_parse(
                    df.loc[fallback_needed, col].astype(str)
                )
                fb_mask = fallback_parsed.notna()
                new_dates.loc[
                    fallback_needed & fb_mask.reindex(df.index, fill_value=False)
                ] = fallback_parsed[fb_mask].dt.strftime('%Y-%m-%d')
        else:
            # 100% pandas fallback
            _safe_stderr(f"   🔄 {col}: 100% pandas fallback parsing...")
            fallback_parsed = _pandas_fallback_parse(df[col])
            fb_mask = fallback_parsed.notna()
            new_dates[fb_mask] = fallback_parsed[fb_mask].dt.strftime('%Y-%m-%d')

        success = new_dates.notna().sum()
        total   = df[col].notna().sum()
        rate    = success / total * 100 if total > 0 else 0
        _safe_stderr(f"   ✅ {col}: {success}/{total} parsed ({rate:.1f}%)")

        date_columns[col] = new_dates
        df = df.drop(columns=[col])

    return df, date_columns


# ── Price parsing ─────────────────────────────────────────────────────────────

_CURRENCY_RE = re.compile(
    r'(USD|EUR|GBP|CAD|AUD|CHF|JPY|CNY|INR|BRL|MXN|[€$£¥₹₩₺₴])',
    flags=re.IGNORECASE
)
_PRICE_KEYWORDS = [
    'price', 'cost', 'amount', 'value', 'salary', 'revenue',
    'total', 'usd', 'eur', 'gbp', 'fee', 'charge', 'wage',
    'income', 'earnings'
]


def _regex_price_parse(series: pd.Series) -> pd.Series:
    """Nettoyage monétaire par regex (fallback)."""
    s = series.fillna('').astype(str).replace('nan', '')
    s = s.str.replace(r'(?i)free|gratuit', '0', regex=True)
    s = s.str.replace(_CURRENCY_RE, '', regex=True)
    s = s.str.replace(r'\s', '', regex=True)
    s = s.apply(
        lambda v: str(v).replace(',', '.')
        if str(v).count(',') == 1 and '.' not in str(v)
        else str(v).replace(',', '')
    )
    return pd.to_numeric(s, errors='coerce')


def clean_prices(df: pd.DataFrame, llm: GeminiLLM) -> pd.DataFrame:
    """
    Conversion des colonnes monétaires en float.
    Stratégie : LLM puis fallback regex.
    """
    for col in df.columns:
        col_lower = col.lower()
        if not any(kw in col_lower for kw in _PRICE_KEYWORDS):
            continue
        if pd.api.types.is_numeric_dtype(df[col]):
            continue

        _safe_stderr(f"   💵 Price detected: {col}")
        raw_values = df[col].dropna().astype(str).tolist()

        llm_mapping = llm.format_prices(col, raw_values) if llm.available else None

        if llm_mapping:
            new_vals = df[col].astype(str).map(
                lambda v: llm_mapping.get(str(v)) if pd.notna(v) else np.nan
            )
            fallback_mask = new_vals.isna() & df[col].notna()
            if fallback_mask.any():
                fb = _regex_price_parse(df.loc[fallback_mask, col])
                new_vals[fallback_mask] = fb.values
            df[col] = pd.to_numeric(new_vals, errors='coerce')
        else:
            df[col] = _regex_price_parse(df[col])

    return df


# ── Negative values ───────────────────────────────────────────────────────────

_ABS_OK_KEYWORDS = [
    'salary', 'wage', 'income', 'revenue', 'price', 'cost',
    'amount', 'fee', 'charge', 'total', 'balance'
]
_NULL_ON_NEGATIVE_KEYWORDS = [
    'age', 'years', 'old', 'tenure',
    'distance', 'height', 'weight',
    'count', 'quantity', 'qty', 'score',
    'rate', 'percent', 'pct'
]


def handle_negatives(df: pd.DataFrame, decisions: dict) -> pd.DataFrame:
    """
    Corrige les valeurs négatives impossibles.

    Strategy:
    - salary/price negative  → abs()   (probable sign error)
    - age/count/score negative → NULL  (cannot guess the real value)
    """
    for col, decision in decisions.items():
        if col not in df.columns:
            continue
        if not pd.api.types.is_numeric_dtype(df[col]):
            continue

        llm_says_positive = decision.get('can_be_negative') is False
        col_lower = col.lower()

        is_abs_col  = any(kw in col_lower for kw in _ABS_OK_KEYWORDS)
        is_null_col = any(kw in col_lower for kw in _NULL_ON_NEGATIVE_KEYWORDS)
        llm_action  = decision.get('negative_action', None)

        should_fix = llm_says_positive or is_abs_col or is_null_col
        if not should_fix:
            continue

        neg_mask  = df[col] < 0
        neg_count = neg_mask.sum()
        if neg_count == 0:
            continue

        if llm_action == 'abs' or (is_abs_col and not is_null_col):
            action = 'abs'
        elif llm_action == 'null' or is_null_col:
            action = 'null'
        else:
            action = 'null'  # conservative default

        if action == 'abs':
            df.loc[neg_mask, col] = df.loc[neg_mask, col].abs()
            _safe_stderr(
                f"   ⚠️  {col}: {neg_count} negatives → abs() (sign error assumed)"
            )
        else:
            df.loc[neg_mask, col] = np.nan
            _safe_stderr(
                f"   ⚠️  {col}: {neg_count} negatives → NULL (human review needed)"
            )

    return df


# ============================================================================
# TEXT QUALITY
# ============================================================================

_GEO_KEYWORDS   = ['city', 'country', 'state', 'region', 'province',
                    'district', 'town', 'village', 'location', 'area',
                    'continent', 'territory', 'county', 'parish']
_EMAIL_KEYWORDS = ['email', 'mail', 'e-mail', 'courriel']
_NAME_KEYWORDS  = ['name', 'firstname', 'lastname', 'first_name', 'last_name',
                   'full_name', 'prenom', 'nom', 'surname']


def _detect_text_column_type(col_name: str) -> str:
    col_lower = col_name.lower().replace('-', '_').replace(' ', '_')
    if any(kw in col_lower for kw in _EMAIL_KEYWORDS):
        return 'email'
    if any(kw in col_lower for kw in _GEO_KEYWORDS):
        return 'geo'
    if any(kw in col_lower for kw in _NAME_KEYWORDS):
        return 'name'
    return 'generic'


def _fuzzy_correct(series: pd.Series, threshold: float = 0.65) -> pd.Series:
    """
    Corrige les typos dans une colonne catégorielle par fuzzy matching.
    La valeur la plus fréquente de chaque cluster devient la valeur canonique.
    """
    from difflib import SequenceMatcher

    value_counts = series.dropna().str.strip().str.lower().value_counts()
    if len(value_counts) == 0:
        return series

    vals = list(value_counts.index)

    clusters = {}
    assigned = {}

    for val in vals:
        if val in assigned:
            continue
        best_score, best_canon = 0, None
        for canon in clusters:
            score = SequenceMatcher(None, val, canon).ratio()
            if score > best_score:
                best_score, best_canon = score, canon

        if best_score >= threshold and best_canon:
            clusters[best_canon].add(val)
            assigned[val] = best_canon
        else:
            clusters[val] = {val}
            assigned[val] = val

    correction_map = {}
    for canon, variants in clusters.items():
        best_count, best_val = -1, canon
        for v in variants:
            if v in value_counts and value_counts[v] > best_count:
                best_count, best_val = value_counts[v], v
        for v in variants:
            correction_map[v] = best_val

    def apply_correction(val):
        if pd.isna(val):
            return val
        return correction_map.get(str(val).strip().lower(), str(val).strip().lower())

    return series.map(apply_correction)


def clean_text_columns(df: pd.DataFrame) -> pd.DataFrame:
    """
    Nettoyage des colonnes textuelles:
      - Strip + NaN normalisation (toutes colonnes)
      - Email    → lowercase
      - Geo      → Title Case
      - Names    → Title Case
      - Catégorielles (≤50 valeurs) → fuzzy correction + Title Case
    """
    _safe_stderr("\n   Text quality normalization...")

    nan_vals = {'nan', 'NaN', 'NAN', 'None', 'none', 'NULL',
                'null', 'N/A', 'n/a', 'NA', 'na', '', ' ', '<NA>'}

    for col in df.columns:
        if not (df[col].dtype == 'object' or str(df[col].dtype) == 'str'):
            continue

        col_type = _detect_text_column_type(col)
        series   = df[col].astype(object).str.strip()
        series   = series.where(~series.isin(nan_vals), other=np.nan)

        if col_type == 'email':
            series = series.str.lower()
            _safe_stderr(f"      {col}: email lowercase applied")

        elif col_type == 'geo':
            before = series.dropna().nunique()
            series = series.str.title()
            _safe_stderr(f"      {col}: geo Title Case ({before} unique values)")

        elif col_type == 'name':
            series = series.str.title()
            _safe_stderr(f"      {col}: name Title Case applied")

        else:
            unique_count = series.dropna().nunique()
            if 2 <= unique_count <= 50:
                before_unique = unique_count
                corrected = _fuzzy_correct(series)
                corrected = corrected.str.title()
                after_unique = corrected.dropna().nunique()
                changed = (
                    series.dropna().str.lower() != corrected.dropna().str.lower()
                ).sum()
                if changed > 0:
                    series = corrected
                    _safe_stderr(
                        f"      {col}: {changed} values corrected, "
                        f"{before_unique} → {after_unique} unique"
                    )
                else:
                    series = corrected

        df[col] = series

    return df


# ============================================================================
# MAIN CLEANER CLASS
# ============================================================================

class DataCleaner:
    """
    Data cleaner v4.1 — sanitize only.
    LLM Gemini used for type detection and format conversion.
    Falls back to heuristics when LLM unavailable.
    """

    def __init__(self, file_path: str, use_llm: bool = True,
                 row_threshold: float = 0.5, col_threshold: float = 0.3,
                 column_types: dict = None):
        self.file_path      = file_path
        self.use_llm        = use_llm
        self.row_threshold  = row_threshold
        self.col_threshold  = col_threshold
        self.column_types   = column_types or {}   # user overrides from frontend
        self.df             = self._load_file(file_path)
        self.llm            = GeminiLLM() if use_llm else self._disabled_llm()

    @staticmethod
    def _disabled_llm() -> GeminiLLM:
        """Return an unavailable GeminiLLM instance without making API calls."""
        inst = GeminiLLM.__new__(GeminiLLM)
        inst.available = False
        inst._api_key  = None
        return inst

    def _load_file(self, path: str) -> pd.DataFrame:
        ext = os.path.splitext(path)[-1].lower()

        try:
            df = None

            if ext in ['.xlsx', '.xls']:
                sheets = pd.read_excel(path, sheet_name=None)
                df = pd.concat(sheets.values(), ignore_index=True)

            elif ext == '.json':
                with open(path, 'r') as f:
                    df = pd.json_normalize(json.load(f))

            elif ext == '.xml':
                df = pd.read_xml(path)

            elif ext in ['.txt', '.csv']:
                for encoding in ['utf-8', 'latin-1', 'iso-8859-1', 'cp1252', 'utf-16']:
                    try:
                        df = pd.read_csv(
                            path, sep=None, engine='python',
                            encoding=encoding, on_bad_lines='skip'
                        )
                        _safe_stderr(f"✅ Loaded with encoding: {encoding}")
                        break
                    except UnicodeDecodeError:
                        continue
                    except Exception:
                        try:
                            df = pd.read_csv(
                                path, encoding=encoding, on_bad_lines='skip'
                            )
                            break
                        except Exception:
                            continue

                if df is None:
                    raise ValueError("Cannot read file with any tested encoding")
            else:
                raise ValueError(f"Unsupported format: {ext}")

            if df is not None:
                for col in df.columns:
                    if df[col].dtype == 'object':
                        df[col] = df[col].astype(str).str.strip().str.lstrip("'")
                        df[col] = df[col].replace(
                            ['nan', 'NaN', 'None', '', 'null', 'NULL',
                             'Null', 'N/A', 'n/a', 'NA'],
                            np.nan
                        )
                _safe_stderr("✅ Global string cleaning applied")

            return df

        except Exception as e:
            raise ValueError(f"Load error: {str(e)}")

    def clean(self) -> pd.DataFrame:
        """
        Cleaning pipeline v4.1:
          1. Basic cleaning (duplicates, empties)
          2. Text quality normalisation
          3. Column type detection via LLM (or heuristic fallback)
          4. Date conversion  (LLM + pandas fallback)
          5. Price conversion (LLM + regex fallback)
          6. Negative value correction
          7. Re-insert date columns in original order
        """
        _safe_stderr(f"\n{'='*60}")
        _safe_stderr(f"📂 {os.path.basename(self.file_path)}")
        _safe_stderr(f"{'='*60}")
        _safe_stderr(f"Rows: {len(self.df)}, Columns: {len(self.df.columns)}")

        original_column_order = self.df.columns.tolist()

        # 1. Basic cleaning
        self.df = clean_basic(self.df, self.row_threshold, self.col_threshold)

        # 2. Text quality
        _safe_stderr("\n🔤 Text quality cleaning...")
        self.df = clean_text_columns(self.df)

        # 3. Column type detection
        _safe_stderr("\n📊 Column type detection...")
        profile   = create_dataset_profile(self.df)
        decisions = self.llm.detect_column_types(profile) if self.llm.available else None
        if not decisions:
            _safe_stderr("📋 Using heuristic fallback for type detection...")
            decisions = create_fallback_type_decisions(self.df)

        # 3b. Apply user overrides from the frontend type picker
        if self.column_types:
            _safe_stderr(f"\n🎯 Applying {len(self.column_types)} user column type override(s)...")
            TYPE_MAP = {
                'date':       {'type': 'date',       'format': None},
                'price':      {'type': 'price',      'format': None},
                'integer':    {'type': 'numeric',    'format': 'integer'},
                'text':       {'type': 'text',       'format': None},
                'identifier': {'type': 'identifier', 'format': None},
            }
            if decisions is None:
                decisions = {}
            for col, user_type in self.column_types.items():
                if col in self.df.columns and user_type in TYPE_MAP:
                    decisions[col] = TYPE_MAP[user_type]
                    _safe_stderr(f"   ✅ {col} → forced to '{user_type}'")

        # 4. Dates
        _safe_stderr(f"\n🔧 Date conversion...")
        self.df, date_columns = clean_dates(self.df, decisions, self.llm)

        # 5. Prices
        _safe_stderr(f"\n💰 Price conversion...")
        self.df = clean_prices(self.df, self.llm)

        # Residual strip on text columns
        for col in self.df.columns:
            if self.df[col].dtype == 'object':
                self.df[col] = self.df[col].str.strip()

        # 6. Negatives
        _safe_stderr(f"\n🔢 Negative values check...")
        self.df = handle_negatives(self.df, decisions)

        # 7. Re-integrate dates
        for col, series in date_columns.items():
            self.df[col] = series
            _safe_stderr(f"   ✅ Date re-integrated: {col}")

        # Restore original column order
        available_columns = [c for c in original_column_order if c in self.df.columns]
        self.df = self.df[available_columns]

        # NULL summary
        null_summary = self.df.isna().sum()
        null_cols    = null_summary[null_summary > 0]
        if len(null_cols) > 0:
            _safe_stderr(f"\n📊 Remaining NULLs (to handle in cross_reference.py):")
            for col, count in null_cols.items():
                pct = count / len(self.df) * 100
                _safe_stderr(f"   • {col}: {count} ({pct:.1f}%)")

        _safe_stderr(
            f"\n✅ Sanitize done: {len(self.df)} rows, {len(self.df.columns)} columns"
        )
        return self.df


# ============================================================================
# CLI
# ============================================================================

def main():
    if len(sys.argv) < 3:
        _safe_stderr(
            "Usage: python data_cleaner.py <input_file> <output_file> [options_json]"
        )
        sys.exit(1)

    input_file  = sys.argv[1]
    output_file = sys.argv[2]

    use_llm      = True
    column_types = {}
    if len(sys.argv) >= 4:
        try:
            options      = json.loads(sys.argv[3])
            use_llm      = options.get('use_llm', True)
            column_types = options.get('column_types', {})
        except Exception:
            pass

    try:
        cleaner  = DataCleaner(input_file, use_llm=use_llm, column_types=column_types)
        df_clean = cleaner.clean()
        df_clean.to_csv(output_file, index=False, quoting=csv.QUOTE_MINIMAL)

        result = {
            'status':        'success',
            'input_file':    input_file,
            'output_file':   output_file,
            'rows':          len(df_clean),
            'columns':       len(df_clean.columns),
            'null_remaining': int(df_clean.isna().sum().sum()),
            'message':       f'Sanitize done: {os.path.basename(input_file)}'
        }
        print(json.dumps(result))

    except Exception as e:
        print(json.dumps({'status': 'error', 'message': str(e)}))
        sys.exit(1)


if __name__ == "__main__":
    main()