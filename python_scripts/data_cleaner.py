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
        import time
        for attempt in range(3):
            try:
                req = urllib.request.Request(
                    self.API_URL + "?key=" + self._api_key,
                    data=payload, headers={"Content-Type": "application/json"}, method="POST"
                )
                with urllib.request.urlopen(req, timeout=60) as r:
                    resp = _j.loads(r.read())
                return resp["candidates"][0]["content"]["parts"][0]["text"]
            except Exception as e:
                if '429' in str(e) and attempt < 2:
                    wait = 30 * (attempt + 1)
                    _safe_stderr(f"WARNING: Gemini rate limit (429), retrying in {wait}s...")
                    time.sleep(wait)
                else:
                    _safe_stderr(f"WARNING: Gemini API error: {e}")
                    return None


def _safe_stderr(*args, **kwargs):
    """Print vers stderr compatible Windows."""
    text = ' '.join(str(a) for a in args)
    try:
        print(text, file=sys.stderr)
    except UnicodeEncodeError:
        safe = text.encode('ascii', errors='replace').decode('ascii')
        print(safe, file=sys.stderr)

# ============================================================================
# GEMINI LLM CLIENT (remplace Gemini — urllib built-in, aucun package requis)
# ============================================================================
# HELPER FUNCTIONS
# ============================================================================

def is_date_column_heuristic(series: pd.Series) -> bool:
    """Détection heuristique basique (fallback si LLM indisponible)"""

    # Les colonnes numériques ne sont JAMAIS des dates
    if pd.api.types.is_numeric_dtype(series):
        return False

    # Seulement les colonnes string/object peuvent être des dates
    if series.dtype not in ['object'] and str(series.dtype) != 'str':
        return False

    col_name_lower = series.name.lower() if series.name else ''

    # Mots-clés exacts — éviter les faux positifs comme "downtime", "overtime",
    # "Average Collection Days", "Payment Days Outstanding"
    # On vérifie que le mot-clé est un mot complet (entouré de séparateurs)
    import re as _re
    DATE_EXACT_KEYWORDS = ['date', 'timestamp', 'birthday', 'birth_date',
                           'created_at', 'updated_at', 'created_on', 'updated_on',
                           'signup_date', 'start_date', 'end_date', 'due_date',
                           'order_date', 'invoice_date', 'delivery_date']

    # Correspondance exacte sur le nom de colonne
    if any(kw == col_name_lower or col_name_lower.endswith('_' + kw) or
           col_name_lower.startswith(kw + '_') or ('_' + kw + '_') in col_name_lower
           for kw in ['date', 'timestamp', 'birthday']):
        # Vérifier que les valeurs ressemblent vraiment à des dates
        sample = series.dropna().head(20).astype(str)
        if sample.str.contains(r'\d{1,4}[/\-]\d{1,2}[/\-]\d{2,4}', regex=True).any():
            return True
        if sample.str.contains(r'\b(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)\b',
                               case=False, regex=True).any():
            return True
        return False

    # Sinon : détecter uniquement par le contenu des valeurs (pas par le nom)
    if series.dtype == 'object' or str(series.dtype) == 'str':
        sample = series.dropna().head(100).astype(str)
        if len(sample) == 0:
            return False

        # Vérifier que les valeurs ne sont pas juste des nombres
        numeric_ratio = pd.to_numeric(sample, errors='coerce').notna().sum() / len(sample)
        if numeric_ratio > 0.5:
            return False  # Colonne numérique stockée en string

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
    'enero': 'January', 'febrero': 'February', 'marzo': 'March',
    'abril': 'April', 'mayo': 'May', 'junio': 'June', 'julio': 'July',
    'agosto': 'August', 'septiembre': 'September', 'octubre': 'October',
    'noviembre': 'November', 'diciembre': 'December',
    'janeiro': 'January', 'fevereiro': 'February', 'marco': 'March',
    'junho': 'June', 'julho': 'July', 'setembro': 'September',
    'outubro': 'October', 'novembro': 'November', 'dezembro': 'December',
    'gennaio': 'January', 'febbraio': 'February', 'aprile': 'April',
    'maggio': 'May', 'giugno': 'June', 'luglio': 'July',
    'settembre': 'September', 'ottobre': 'October', 'dicembre': 'December',
}

_FOREIGN_RE = re.compile(
    r'\b(' + '|'.join(re.escape(k) for k in sorted(_FOREIGN_MONTHS, key=len, reverse=True)) + r')\b',
    flags=re.IGNORECASE
)


def _normalize_foreign_months(values: list) -> tuple:
    """Traduit les mois non-anglais. Retourne (valeurs_normalisées, dict_traduction_inverse)."""
    normalized, translations = [], {}
    for v in values:
        norm = _FOREIGN_RE.sub(lambda m: _FOREIGN_MONTHS.get(m.group(0).lower(), m.group(0)), v)
        normalized.append(norm)
        if norm != v:
            translations[norm] = v
    return normalized, translations


_DATE_RE = re.compile(r'^\d{4}-\d{2}-\d{2}$')


def flatten_llm_date_response(mapping: dict, raw_vals: list) -> dict:
    """Normalise la structure JSON retournée par le LLM."""
    raw_set = set(raw_vals)
    if len(mapping) == 1:
        only_val = next(iter(mapping.values()))
        if isinstance(only_val, dict):
            mapping = only_val
    flat = {}
    for k, v in mapping.items():
        if isinstance(v, dict):
            iso = None
            for subk in ('iso', 'date', 'result', 'parsed', 'formatted', 'value'):
                if subk in v and isinstance(v[subk], str) and _DATE_RE.match(v[subk]):
                    iso = v[subk]; break
            if iso is None:
                for subv in v.values():
                    if isinstance(subv, str) and _DATE_RE.match(subv):
                        iso = subv; break
            flat[k] = iso
        elif isinstance(v, str):
            flat[k] = v if _DATE_RE.match(v) else None
        else:
            flat[k] = None
    matched = sum(1 for k in flat if k in raw_set)
    if matched < len(raw_vals) * 0.3 and mapping:
        inverted = {v: k if _DATE_RE.match(k) else None
                    for k, v in mapping.items() if isinstance(v, str) and v in raw_set}
        if sum(1 for v in inverted.values() if v) > sum(1 for v in flat.values() if v):
            flat = inverted
    return flat


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


# ============================================================================
# TEXT QUALITY — normalisation des colonnes textuelles
# ============================================================================

# Colonnes géographiques → Title Case (city, country, state, region...)
_GEO_KEYWORDS = ['city', 'country', 'state', 'region', 'province',
                  'district', 'town', 'village', 'location', 'area',
                  'continent', 'territory', 'county', 'parish']

# Colonnes email → toujours lowercase
_EMAIL_KEYWORDS = ['email', 'mail', 'e-mail', 'courriel']

# Colonnes nom propre → Title Case
_NAME_KEYWORDS = ['name', 'firstname', 'lastname', 'first_name', 'last_name',
                   'full_name', 'prenom', 'nom', 'surname']


def _detect_text_column_type(col_name: str) -> str:
    """Retourne 'geo', 'email', 'name', ou 'generic' selon le nom de colonne."""
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
    
    Stratégie :
    1. Grouper les valeurs par similarité pour trouver les clusters
    2. La valeur la plus fréquente de chaque cluster = canonique
    3. Toutes les autres → remplacées par la canonique

    Ex: 'Mnoitor','Mointor','Montior' → 'Monitor'
        'Cahrger','Chagrer','Charegr' → 'Charger'
        'phoenix','PHOENIX','  Phoenix  ' → 'Phoenix'
    """
    from difflib import SequenceMatcher

    value_counts = series.dropna().str.strip().str.lower().value_counts()
    if len(value_counts) == 0:
        return series

    vals = list(value_counts.index)  # triés par fréquence décroissante

    # Construire des clusters par similarité
    clusters = {}   # canonical_lower → set of variants
    assigned = {}   # val → canonical_lower

    for val in vals:
        if val in assigned:
            continue
        # Chercher si proche d'un canonique existant
        best_score = 0
        best_canon = None
        for canon in clusters:
            score = SequenceMatcher(None, val, canon).ratio()
            if score > best_score:
                best_score = score
                best_canon = canon

        if best_score >= threshold and best_canon:
            clusters[best_canon].add(val)
            assigned[val] = best_canon
        else:
            # Nouveau cluster
            clusters[val] = {val}
            assigned[val] = val

    # Map : variant_lower → canonical (le plus fréquent du cluster)
    correction_map = {}
    for canon, variants in clusters.items():
        # Trouver le plus fréquent dans ce cluster
        best_count = -1
        best_val = canon
        for v in variants:
            if v in value_counts and value_counts[v] > best_count:
                best_count = value_counts[v]
                best_val = v
        for v in variants:
            correction_map[v] = best_val

    def apply_correction(val):
        if pd.isna(val):
            return val
        v_lower = str(val).strip().lower()
        return correction_map.get(v_lower, v_lower)

    return series.map(apply_correction)


def clean_text_columns(df: pd.DataFrame) -> pd.DataFrame:
    """
    Nettoyage des colonnes textuelles :
    
    1. Toutes les colonnes object :
       - strip espaces début/fin
       - 'NAN', 'nan', 'None', '' → NaN
    
    2. Colonnes email :
       - lowercase systématique
       - strip
    
    3. Colonnes géographiques (city, country, state...) :
       - Title Case (phoenix → Phoenix, SAN DIEGO → San Diego)
       - strip
    
    4. Colonnes noms propres :
       - Title Case
    
    5. Colonnes catégorielles avec typos (product, category...) :
       - Fuzzy matching pour corriger les erreurs de frappe
       - Seulement si peu de valeurs uniques (<= 50 valeurs distinctes)
    """
    _safe_stderr("\n   Text quality normalization...")

    for col in df.columns:
        if not (df[col].dtype == 'object' or str(df[col].dtype) == 'str'):
            continue

        col_type = _detect_text_column_type(col)
        series = df[col].copy()

        # ── Strip + NaN normalization (toutes colonnes) ──────────────────
        # Force object dtype — StringDtype (pandas 2.x) casse where() + NaN
        series = series.astype(object).str.strip()
        nan_vals = {'nan', 'NaN', 'NAN', 'None', 'none', 'NULL',
                    'null', 'N/A', 'n/a', 'NA', 'na', '', ' ', '<NA>'}
        series = series.where(~series.isin(nan_vals), other=np.nan)

        # ── Email → lowercase ─────────────────────────────────────────────
        if col_type == 'email':
            series = series.str.lower()
            _safe_stderr(f"      {col}: email lowercase applied")

        # ── Géographique → Title Case ─────────────────────────────────────
        elif col_type == 'geo':
            before = series.dropna().nunique()
            series = series.str.title()
            after = series.dropna().nunique()
            _safe_stderr(f"      {col}: geo Title Case ({before} → {after} unique values)")

        # ── Nom propre → Title Case ───────────────────────────────────────
        elif col_type == 'name':
            series = series.str.title()
            _safe_stderr(f"      {col}: name Title Case applied")

        # ── Catégorielle générique → fuzzy correction si peu de valeurs ──
        else:
            unique_count = series.dropna().nunique()
            if 2 <= unique_count <= 50:
                before_unique = unique_count
                corrected = _fuzzy_correct(series)
                # Title Case sur le résultat
                corrected = corrected.str.title()
                after_unique = corrected.dropna().nunique()
                changed = (series.dropna().str.lower() != corrected.dropna().str.lower()).sum()
                if changed > 0:
                    series = corrected
                    _safe_stderr(f"      {col}: {changed} values corrected, {before_unique} → {after_unique} unique")
                else:
                    series = corrected

        df[col] = series

    return df


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


# Colonnes où abs() a du sens (erreur de signe probable : -1200 → 1200)
_ABS_OK_KEYWORDS = ['salary', 'wage', 'income', 'revenue', 'price', 'cost',
                    'amount', 'fee', 'charge', 'total', 'balance']

# Colonnes où un négatif impossible → NULL (on ne sait pas la vraie valeur)
_NULL_ON_NEGATIVE_KEYWORDS = ['age', 'years', 'old', 'tenure',
                               'distance', 'height', 'weight',
                               'count', 'quantity', 'qty', 'score',
                               'rate', 'percent', 'pct']


def handle_negatives(df: pd.DataFrame, decisions: dict) -> pd.DataFrame:
    """
    Corrige les valeurs négatives impossibles.
    
    Stratégie selon le contexte :
    - salary/price négatif  → abs()  (probablement erreur de signe : -1200 → 1200)
    - age/count/score négatif → NULL  (on ne peut pas deviner la vraie valeur)
    
    Principe : ne jamais inventer une valeur quand on ne sait pas.
    """
    for col, decision in decisions.items():
        if col not in df.columns:
            continue
        if not pd.api.types.is_numeric_dtype(df[col]):
            continue

        llm_says_positive = decision.get('can_be_negative') is False
        col_lower = col.lower()

        # Déterminer l'action selon le type de colonne
        is_abs_col  = any(kw in col_lower for kw in _ABS_OK_KEYWORDS)
        is_null_col = any(kw in col_lower for kw in _NULL_ON_NEGATIVE_KEYWORDS)

        # LLM peut aussi forcer abs ou null
        llm_action = decision.get('negative_action', None)  # 'abs' ou 'null'

        should_fix = llm_says_positive or is_abs_col or is_null_col
        if not should_fix:
            continue

        neg_mask = df[col] < 0
        neg_count = neg_mask.sum()
        if neg_count == 0:
            continue

        # Décider l'action
        if llm_action == 'abs' or (is_abs_col and not is_null_col):
            action = 'abs'
        elif llm_action == 'null' or is_null_col:
            action = 'null'
        else:
            action = 'null'  # défaut conservateur

        if action == 'abs':
            df.loc[neg_mask, col] = df.loc[neg_mask, col].abs()
            _safe_stderr(f"   WARNING {col}: {neg_count} negatives → abs() (sign error assumed)")
        else:
            df.loc[neg_mask, col] = np.nan
            _safe_stderr(f"   WARNING {col}: {neg_count} negatives → NULL (value unknown, human review needed)")

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

        # 1b. Nettoyage qualité texte
        _safe_stderr("\n🔤 Text quality cleaning...")
        self.df = clean_text_columns(self.df)

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