#!/usr/bin/env python3
"""
DATA CLEANER - Version 4.0
===========================
Rôle : Nettoyage de base uniquement (sanitize)
- Suppression doublons / lignes-colonnes vides
- Détection et conversion des types via LLM Gemini (dates, prix, numériques)
- Correction des formats (DD/MM vs MM/DD, symboles monétaires, mois abrégés)
- Valeurs négatives impossibles → valeur absolue
NE PAS : imputer, enrichir, valider règles métier (→ cross_reference.py)

v4.0 : LLM Gemini utilisé pour TOUT le formatage des dates (plus de regex fragile)
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

# ============================================================================
# GEMINI LLM CLIENT (remplace Gemini)
# ============================================================================

class GeminiLLM:
    """Client Gemini via REST (urllib) — meme interface que GeminiLLM.
    Utilise GEMINI_API_KEY comme variable d'environnement.
    """
    API_URL = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent"

    def __init__(self):
        self.available = False
        self.model = "gemini-2.0-flash"
        import os
        api_key = os.environ.get("GEMINI_API_KEY")
        if not api_key:
            _safe_stderr("WARNING: GEMINI_API_KEY non definie (mode fallback active)")
            return
        try:
            import urllib.request, json as _j
            data = _j.dumps({
                "contents": [{"parts": [{"text": "test"}]}],
                "generationConfig": {"maxOutputTokens": 5}
            }).encode()
            req = urllib.request.Request(
                self.API_URL + "?key=" + api_key,
                data=data, headers={"Content-Type": "application/json"}, method="POST"
            )
            with urllib.request.urlopen(req, timeout=10) as r:
                r.read()
            self._api_key = api_key
            self.available = True
            _safe_stderr(f"OK Gemini LLM initialized ({self.model})")
        except Exception as e:
            _safe_stderr(f"WARNING: Gemini LLM not available: {e}")

    def call(self, prompt: str, max_tokens: int = 1000):
        if not self.available:
            return None
        import urllib.request, json as _j
        payload = _j.dumps({
            "contents": [{"parts": [{"text": prompt}]}],
            "generationConfig": {"maxOutputTokens": max_tokens, "temperature": 0}
        }).encode()
        try:
            req = urllib.request.Request(
                self.API_URL + "?key=" + self._api_key,
                data=payload, headers={"Content-Type": "application/json"}, method="POST"
            )
            with urllib.request.urlopen(req, timeout=60) as r:
                resp = _j.loads(r.read())
            return resp["candidates"][0]["content"]["parts"][0]["text"]
        except Exception as e:
            _safe_stderr(f"WARNING: Gemini API error: {e}")
            return None


def _safe_stderr(*args, **kwargs):
    """Print vers stderr compatible Windows (gère les emojis/accents)."""
    import io
    kwargs['file'] = sys.stderr
    try:
        _safe_stderr(*args, **kwargs)
    except UnicodeEncodeError:
        # Fallback : encoder en ASCII avec remplacement
        text = ' '.join(str(a) for a in args)
        safe = text.encode('ascii', errors='replace').decode('ascii')
        print(safe)

# ============================================================================
# GEMINI LLM CLIENT (remplace Gemini — urllib built-in, aucun package requis)
# ============================================================================

class GeminiLLM:
    """Client Gemini 2.0 Flash via REST.
    Variable d'environnement requise : GEMINI_API_KEY
    Interface identique a l'ancien GeminiLLM : .available, .model, .call()
    """
    _API_BASE = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent"

    def detect_column_types(self, profile: dict) -> dict | None:
        """Détecte les types de colonnes (date, numérique, catégoriel, etc.)"""
        if not self.available:
            return None

        prompt = f"""Analyze this dataset profile and classify each column type.

Dataset profile:
{json.dumps(profile, indent=2)}

For EACH column, respond with a JSON object:
- is_date: boolean
- can_be_negative: boolean (false for prices, ages, quantities; true for temperatures, balances, profits)
- reason: brief explanation

Respond ONLY valid JSON (no markdown):
{{
  "column_name": {{
    "is_date": true/false,
    "can_be_negative": true/false,
    "reason": "..."
  }}
}}"""

        try:
            _safe_stderr("🤖 Gemini LLM → type detection...")
            resp = self.client.chat.completions.create(
                model=self.model,
                messages=[{"role": "user", "content": prompt}],
                temperature=0,
                max_tokens=1500,
                response_format={"type": "json_object"}
            )
            result = json.loads(resp.choices[0].message.content)
            _safe_stderr(f"✅ Types detected for {len(result)} columns")
            return result
        except Exception as e:
            _safe_stderr(f"⚠️  LLM type detection error: {e}")
            return None

    # ------------------------------------------------------------------
    # 2. Formatage LLM des dates (cœur de la v4)
    # ------------------------------------------------------------------
    def format_dates(self, column_name: str, raw_values: list[str]) -> dict | None:
        """
        Envoie une liste de valeurs brutes au LLM et récupère :
        {
          "original_value": "YYYY-MM-DD or null",
          ...
        }
        Le LLM comprend n'importe quel format humain (01/mar/2003, March 1 2003, etc.)
        """
        if not self.available:
            return None

        # On envoie maximum 200 valeurs uniques pour économiser les tokens
        unique_vals_raw = list(dict.fromkeys(str(v) for v in raw_values if v is not None and str(v).strip()))[:30]

        if not unique_vals_raw:
            return None

        # Pré-traitement : normaliser les mois non-anglais (août→August, mai→May, etc.)
        unique_vals, fr_translations = _normalize_foreign_months(unique_vals_raw)

        example_in = unique_vals[:3]
        prompt = f"""You are a date parser. Your ONLY job: convert date strings to ISO format.

INPUT — list of date strings for column "{column_name}":
{json.dumps(unique_vals, ensure_ascii=False)}

OUTPUT RULES (STRICT):
1. Return a FLAT JSON object (no nesting, no arrays)
2. Each key   = the EXACT original string from the input list
3. Each value = the ISO date string "YYYY-MM-DD", or null if unparseable
4. Every input string must appear as a key — do not skip any
5. No extra keys, no explanations, no markdown

EXAMPLE (structure only):
Input:  {json.dumps(example_in, ensure_ascii=False)}
Output: {{"01/mar/2003": "2003-03-01", "15-Jun-1990": "1990-06-15", "1990-07-22": "1990-07-22"}}

Respond with ONLY the JSON object."""

        try:
            resp = self.client.chat.completions.create(
                model=self.model,
                messages=[{"role": "user", "content": prompt}],
                temperature=0,
                max_tokens=1000,
            )
            raw_text = resp.choices[0].message.content.strip()
            import re as _re
            m = _re.search(r'\{.*\}', raw_text, _re.DOTALL)
            if not m:
                return None
            raw_mapping = json.loads(m.group(0))
            mapping_normalized = flatten_llm_date_response(raw_mapping, unique_vals)
            # Restaurer les clés originales (avant traduction des mois)
            mapping = {}
            for norm_key, iso_val in mapping_normalized.items():
                original_key = fr_translations.get(norm_key, norm_key)
                mapping[original_key] = iso_val
            ok_count = sum(1 for v in mapping.values() if v and v != "null")
            _safe_stderr(f"   ✅ LLM formatted {ok_count}/{len(unique_vals)} unique dates for '{column_name}'")
            return mapping
        except Exception as e:
            _safe_stderr(f"   ⚠️  LLM date formatting error for '{column_name}': {e}")
            return None

    # ------------------------------------------------------------------
    # 3. Formatage LLM des prix (nettoyage monétaire)
    # ------------------------------------------------------------------
    def format_prices(self, column_name: str, raw_values: list[str]) -> dict | None:
        """
        Convertit les valeurs monétaires en float.
        "USD 25.50" → 25.5, "1 200€" → 1200.0, "Free" → 0.0
        """
        if not self.available:
            return None

        unique_vals = list(dict.fromkeys(str(v) for v in raw_values if v is not None and str(v).strip()))[:30]

        if not unique_vals:
            return None

        prompt = f"""You are a price/amount parser. Convert ALL monetary strings below to plain float numbers.

Column name: "{column_name}"
Values to convert:
{json.dumps(unique_vals)}

Rules:
- Output ONLY a flat JSON object mapping each original string to a float or null
- "Free", "free", "gratuit" → 0.0
- Remove ALL currency symbols: $, €, £, ¥, USD, EUR, GBP, etc.
- Remove spaces used as thousand separators: "1 200" → 1200.0
- Commas as decimal separators: "25,50" → 25.5
- Commas as thousand separators: "1,200" → 1200.0 (use context)
- Null for truly unparseable values
- No markdown, only JSON

Example: {{"USD 25.50": 25.5, "1 200€": 1200.0, "Free": 0.0, "N/A": null}}"""

        try:
            resp = self.client.chat.completions.create(
                model=self.model,
                messages=[{"role": "user", "content": prompt}],
                temperature=0,
                max_tokens=1000,
            )
            raw = resp.choices[0].message.content.strip()
            # Extraire le JSON même sans response_format
            import re as _re
            m = _re.search(r'\{.*\}', raw, _re.DOTALL)
            if not m:
                return None
            mapping = json.loads(m.group(0))
            _safe_stderr(f"   ✅ LLM formatted {len(mapping)} unique price values for '{column_name}'")
            return mapping
        except Exception as e:
            _safe_stderr(f"   ⚠️  LLM price formatting error for '{column_name}': {e}")
            return None


# ============================================================================
# HELPER FUNCTIONS
# ============================================================================

def is_date_column_heuristic(series: pd.Series) -> bool:
    """Détection heuristique basique (fallback si LLM indisponible)"""
    date_keywords = ['date', 'day', 'time', 'birthday', 'timestamp', 'created', 'updated', 'birth']
    col_name_lower = series.name.lower() if series.name else ''

    if any(keyword in col_name_lower for keyword in date_keywords):
        return True

    if series.dtype == 'object':
        sample = series.dropna().head(100).astype(str)
        if len(sample) == 0:
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
            "null_percentage": round(float(df[col].isna().sum() / len(df) * 100), 1) if len(df) > 0 else 0,
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
    """Détection de types basique si LLM non disponible"""
    decisions = {}
    for col in df.columns:
        decisions[col] = {
            "is_date": is_date_column_heuristic(df[col]),
            "can_be_negative": True,
            "reason": "Fallback heuristic (LLM unavailable)"
        }
    return decisions


# ============================================================================
# CLEANING FUNCTIONS
# ============================================================================

def clean_basic(df: pd.DataFrame, row_thresh=0.5, col_thresh=0.3) -> pd.DataFrame:
    """Nettoyage de base : doublons, lignes/colonnes vides"""
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
        _safe_stderr(f"   🗑️  {before_cols - len(df.columns)} columns removed (>{int((1-col_thresh)*100)}% NULL)")

    return df


# ------------------------------------------------------------------
# Dates — v4 : LLM en premier, fallback pandas multi-format
# ------------------------------------------------------------------

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
    """Tente de parser les dates restantes avec pandas multi-format.
    Normalise les mois non-anglais AVANT le parsing (août→August, mai→May, etc.)
    """
    # Normaliser les mois étrangers sur toute la série d'abord
    normalized = series.copy()
    mask_not_null = series.notna()
    if mask_not_null.any():
        normalized[mask_not_null] = series[mask_not_null].astype(str).apply(
            lambda v: _FOREIGN_RE.sub(lambda m: _FOREIGN_MONTHS.get(m.group(0).lower(), m.group(0)), v)
        )

    parsed = pd.Series(pd.NaT, index=series.index)

    for fmt in FALLBACK_DATE_FORMATS:
        still_null = parsed.isna() & normalized.notna()
        if not still_null.any():
            break
        chunk = pd.to_datetime(normalized[still_null], format=fmt, errors='coerce')
        parsed[still_null] = chunk

    # Dernier recours : pandas infer
    still_null = parsed.isna() & normalized.notna()
    if still_null.any():
        chunk = pd.to_datetime(normalized[still_null], errors='coerce')
        parsed[still_null] = chunk

    return parsed


def clean_dates(df: pd.DataFrame, decisions: dict, llm: GeminiLLM) -> tuple[pd.DataFrame, dict]:
    """
    Conversion des colonnes de dates en YYYY-MM-DD.
    Stratégie v4 :
      1. LLM Gemini (mapping original_value → YYYY-MM-DD)
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

        # --- Étape 1 : LLM Gemini ---
        llm_mapping = llm.format_dates(col, raw_values) if llm.available else None

        new_dates = pd.Series(np.nan, index=df.index, dtype='object')

        if llm_mapping:
            # Appliquer le mapping LLM
            mapped = raw_series.map(lambda v: llm_mapping.get(str(v)) if pd.notna(v) else np.nan)
            # Valeurs mappées et non nulles
            valid_mask = mapped.notna() & (mapped != 'null') & (mapped != '')
            new_dates[valid_mask] = mapped[valid_mask]

            # Valeurs non mappées ou null → fallback pandas
            fallback_needed = df[col].notna() & ~valid_mask
            if fallback_needed.any():
                _safe_stderr(f"   🔄 {col}: pandas fallback for {fallback_needed.sum()} unmapped values...")
                fallback_parsed = _pandas_fallback_parse(df.loc[fallback_needed, col].astype(str))
                fb_mask = fallback_parsed.notna()
                new_dates.loc[fallback_needed & fb_mask.reindex(df.index, fill_value=False)] = \
                    fallback_parsed[fb_mask].dt.strftime('%Y-%m-%d')
        else:
            # Pas de LLM → 100% pandas fallback
            _safe_stderr(f"   🔄 {col}: 100% pandas fallback parsing...")
            fallback_parsed = _pandas_fallback_parse(df[col])
            fb_mask = fallback_parsed.notna()
            new_dates[fb_mask] = fallback_parsed[fb_mask].dt.strftime('%Y-%m-%d')

        success = new_dates.notna().sum()
        total = df[col].notna().sum()
        rate = success / total * 100 if total > 0 else 0
        _safe_stderr(f"   ✅ {col}: {success}/{total} parsed ({rate:.1f}%)")

        date_columns[col] = new_dates
        df = df.drop(columns=[col])

    return df, date_columns


# ------------------------------------------------------------------
# Prix — v4 : LLM en premier, fallback regex
# ------------------------------------------------------------------

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
    """Nettoyage monétaire par regex (fallback)"""
    s = series.fillna('').astype(str).replace('nan', '')
    s = s.str.replace(r'(?i)free|gratuit', '0', regex=True)
    s = s.str.replace(_CURRENCY_RE, '', regex=True)
    s = s.str.replace(r'\s', '', regex=True)
    # Si virgule semble être séparateur décimal (pas de point après)
    s = s.apply(lambda v: str(v).replace(',', '.') if str(v).count(',') == 1 and '.' not in str(v) else str(v).replace(',', ''))
    return pd.to_numeric(s, errors='coerce')


def clean_prices(df: pd.DataFrame, llm: GeminiLLM) -> pd.DataFrame:
    """
    Conversion des colonnes monétaires en float.
    Stratégie v4 : LLM puis fallback regex.
    """
    for col in df.columns:
        col_lower = col.lower()
        if not any(kw in col_lower for kw in _PRICE_KEYWORDS):
            continue

        # Vérifier si la colonne contient des valeurs non numériques
        sample = df[col].dropna().head(50)
        if pd.api.types.is_numeric_dtype(df[col]):
            # Déjà numérique, rien à faire sauf strip éventuel
            continue

        _safe_stderr(f"   💵 Price detected: {col}")
        raw_values = df[col].dropna().astype(str).tolist()

        llm_mapping = llm.format_prices(col, raw_values) if llm.available else None

        if llm_mapping:
            new_vals = df[col].astype(str).map(
                lambda v: llm_mapping.get(str(v)) if pd.notna(v) else np.nan
            )
            # Pour les non mappés, regex fallback
            fallback_mask = new_vals.isna() & df[col].notna()
            if fallback_mask.any():
                fb = _regex_price_parse(df.loc[fallback_mask, col])
                new_vals[fallback_mask] = fb.values
            df[col] = pd.to_numeric(new_vals, errors='coerce')
        else:
            df[col] = _regex_price_parse(df[col])

    return df


# ------------------------------------------------------------------
# Négatifs
# ------------------------------------------------------------------

_ALWAYS_POSITIVE_KEYWORDS = [
    'salary', 'wage', 'income', 'revenue', 'earnings',
    'price', 'cost', 'amount', 'fee', 'charge',
    'age', 'years', 'old',
    'distance', 'length', 'width', 'height', 'weight',
    'count', 'quantity', 'qty', 'number', 'total'
]


def handle_negatives(df: pd.DataFrame, decisions: dict) -> pd.DataFrame:
    """Corrige les valeurs négatives incorrectes → valeur absolue"""
    for col, decision in decisions.items():
        if col not in df.columns:
            continue
        if not pd.api.types.is_numeric_dtype(df[col]):
            continue

        llm_says_positive = decision.get('can_be_negative') is False
        forced_positive = any(kw in col.lower() for kw in _ALWAYS_POSITIVE_KEYWORDS)

        if llm_says_positive or forced_positive:
            neg_count = (df[col] < 0).sum()
            if neg_count > 0:
                reason = "LLM" if llm_says_positive else "keyword rule"
                _safe_stderr(f"   ⚠️  {col}: {neg_count} negatives → abs() [{reason}]")
                df.loc[df[col] < 0, col] = df.loc[df[col] < 0, col].abs()

    return df


# ============================================================================
# MAIN CLEANER CLASS
# ============================================================================

class DataCleaner:
    """
    Data cleaner v4 — sanitize uniquement.
    Le LLM Gemini est utilisé pour toutes les décisions de formatage.
    """

    def __init__(self, file_path: str, use_llm: bool = True,
                 row_threshold: float = 0.5, col_threshold: float = 0.3):
        self.file_path = file_path
        self.use_llm = use_llm
        self.row_threshold = row_threshold
        self.col_threshold = col_threshold
        self.df = self._load_file(file_path)
        self.llm = GeminiLLM() if use_llm else GeminiLLM.__new__(GeminiLLM)
        if not use_llm:
            self.llm.available = False

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
                        df = pd.read_csv(path, sep=None, engine='python',
                                         encoding=encoding, on_bad_lines='skip')
                        _safe_stderr(f"✅ Loaded with encoding: {encoding}")
                        break
                    except UnicodeDecodeError:
                        continue
                    except Exception:
                        try:
                            df = pd.read_csv(path, encoding=encoding, on_bad_lines='skip')
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
                            ['nan', 'NaN', 'None', '', 'null', 'NULL', 'Null', 'N/A', 'n/a', 'NA'],
                            np.nan
                        )
                _safe_stderr("✅ Global string cleaning applied")

            return df

        except Exception as e:
            raise ValueError(f"Load error: {str(e)}")

    def clean(self) -> pd.DataFrame:
        """
        Pipeline de nettoyage v4 :
        1. Nettoyage de base (doublons, vides)
        2. Détection des types via LLM
        3. Conversion des dates (LLM + fallback pandas)
        4. Conversion des prix (LLM + fallback regex)
        5. Correction des négatifs
        6. Restauration de l'ordre des colonnes
        """
        _safe_stderr(f"\n{'='*60}")
        _safe_stderr(f"📂 {os.path.basename(self.file_path)}")
        _safe_stderr(f"{'='*60}")
        _safe_stderr(f"Rows: {len(self.df)}, Columns: {len(self.df.columns)}")

        original_column_order = self.df.columns.tolist()

        # 1. Nettoyage de base
        self.df = clean_basic(self.df, self.row_threshold, self.col_threshold)

        # 2. Profil + détection types
        _safe_stderr("\n📊 Column type detection...")
        profile = create_dataset_profile(self.df)

        decisions = self.llm.detect_column_types(profile) if self.llm.available else None
        if not decisions:
            _safe_stderr("📋 Using heuristic fallback for type detection...")
            decisions = create_fallback_type_decisions(self.df)

        # 3. Dates (LLM + fallback)
        _safe_stderr(f"\n🔧 Date conversion...")
        self.df, date_columns = clean_dates(self.df, decisions, self.llm)

        # 4. Prix (LLM + fallback)
        _safe_stderr(f"\n💰 Price conversion...")
        self.df = clean_prices(self.df, self.llm)

        # Strip résiduel sur les colonnes texte
        for col in self.df.columns:
            if self.df[col].dtype == 'object':
                self.df[col] = self.df[col].str.strip()

        # 5. Négatifs
        _safe_stderr(f"\n🔢 Negative values check...")
        self.df = handle_negatives(self.df, decisions)

        # 6. Réintégrer les dates
        for col, series in date_columns.items():
            self.df[col] = series
            _safe_stderr(f"   ✅ Date re-integrated: {col}")

        # 7. Restaurer l'ordre des colonnes
        available_columns = [c for c in original_column_order if c in self.df.columns]
        self.df = self.df[available_columns]

        # Résumé NULL restants
        null_summary = self.df.isna().sum()
        null_cols = null_summary[null_summary > 0]
        if len(null_cols) > 0:
            _safe_stderr(f"\n📊 Remaining NULLs (to handle in cross_reference.py):")
            for col, count in null_cols.items():
                pct = count / len(self.df) * 100
                _safe_stderr(f"   • {col}: {count} ({pct:.1f}%)")

        _safe_stderr(f"\n✅ Sanitize done: {len(self.df)} rows, {len(self.df.columns)} columns")
        return self.df


# ============================================================================
# CLI
# ============================================================================

def main():
    if len(sys.argv) < 3:
        _safe_stderr("Usage: python data_cleaner.py <input_file> <output_file> [options_json]")
        sys.exit(1)

    input_file = sys.argv[1]
    output_file = sys.argv[2]

    use_llm = True
    if len(sys.argv) >= 4:
        try:
            options = json.loads(sys.argv[3])
            use_llm = options.get('use_llm', True)
        except Exception:
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
            'message': f'Sanitize done: {os.path.basename(input_file)}'
        }
        print(json.dumps(result))

    except Exception as e:
        print(json.dumps({'status': 'error', 'message': str(e)}))
        sys.exit(1)


if __name__ == "__main__":
    main()