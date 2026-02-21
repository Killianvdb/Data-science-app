#!/usr/bin/env python3
"""
DATE UTILS - Module partagé
============================
Normalisation et parsing de dates utilisé par :
  - data_cleaner.py
  - cross_reference.py

Stratégie :
  1. Normalisation des mois non-anglais (août→August, mai→May, etc.)
  2. LLM Gemini (si disponible) pour les formats complexes
  3. Fallback pandas multi-format
"""
import re
import pandas as pd
import numpy as np

# ============================================================
# MOIS NON-ANGLAIS → ANGLAIS
# ============================================================
_FOREIGN_MONTHS = {
    # Français
    'janvier': 'January', 'février': 'February', 'fevrier': 'February',
    'mars': 'March', 'avril': 'April', 'mai': 'May', 'juin': 'June',
    'juillet': 'July', 'août': 'August', 'aout': 'August',
    'septembre': 'September', 'octobre': 'October', 'novembre': 'November',
    'décembre': 'December', 'decembre': 'December',
    # Abréviations françaises
    'jan': 'Jan', 'fév': 'Feb', 'fev': 'Feb', 'avr': 'Apr',
    'juil': 'Jul', 'aoû': 'Aug', 'aou': 'Aug',
    'sep': 'Sep', 'oct': 'Oct', 'nov': 'Nov', 'déc': 'Dec', 'dec': 'Dec',
    # Espagnol
    'enero': 'January', 'febrero': 'February', 'marzo': 'March',
    'abril': 'April', 'mayo': 'May', 'junio': 'June', 'julio': 'July',
    'agosto': 'August', 'septiembre': 'September', 'octubre': 'October',
    'noviembre': 'November', 'diciembre': 'December',
    # Portugais
    'janeiro': 'January', 'fevereiro': 'February', 'marco': 'March',
    'junho': 'June', 'julho': 'July', 'setembro': 'September',
    'outubro': 'October', 'novembro': 'November', 'dezembro': 'December',
    # Italien
    'gennaio': 'January', 'febbraio': 'February', 'aprile': 'April',
    'maggio': 'May', 'giugno': 'June', 'luglio': 'July',
    'settembre': 'September', 'ottobre': 'October', 'dicembre': 'December',
}

_FOREIGN_RE = re.compile(
    r'\b(' + '|'.join(re.escape(k) for k in sorted(_FOREIGN_MONTHS, key=len, reverse=True)) + r')\b',
    flags=re.IGNORECASE
)

def normalize_foreign_months(val: str) -> str:
    """Traduit les mois non-anglais en anglais. Ex: '08-août-1999' → '08-August-1999'"""
    def replace(m):
        return _FOREIGN_MONTHS.get(m.group(0).lower(), m.group(0))
    return _FOREIGN_RE.sub(replace, val)

# ============================================================
# DÉTECTION DE COLONNE DATE
# ============================================================
# Mots-clés exacts pour détection de colonne date
# Utilise une correspondance de MOT ENTIER pour éviter les faux positifs
# ("category" ne doit pas matcher "at", "customer" ne doit pas matcher "to")
_DATE_KEYWORDS_RE = re.compile(
    r'\b(date|day|time|birthday|timestamp|created|updated|birth|'
    r'delivery|order_date|signup|expiry|start_date|end_date|'
    r'order|shipdate|duedate|closedate|opendate|saledate)\b',
    flags=re.IGNORECASE
)
# Noms de colonnes complets connus pour être des dates
_DATE_COL_EXACT = {'date', 'day', 'timestamp', 'created_at', 'updated_at',
                   'birth_date', 'birthdate', 'due_date', 'close_date',
                   'start_date', 'end_date', 'ship_date', 'order_date',
                   'delivery_date', 'signup_date', 'expiry_date', 'last_update',
                   'created', 'updated', 'datetime'}

def is_date_column(series: pd.Series) -> bool:
    """Heuristique : détecte si une colonne contient des dates."""
    col_lower = str(series.name).lower() if series.name else ''
    # 1. Correspondance exacte du nom de colonne
    if col_lower in _DATE_COL_EXACT:
        return True
    # 2. Correspondance par mot entier dans le nom (évite "category"→"at", "customer"→"to")
    if _DATE_KEYWORDS_RE.search(col_lower):
        return True
    if series.dtype == 'object':
        sample = series.dropna().head(50).astype(str)
        if len(sample) == 0:
            return False
        date_pattern = sample.str.contains(
            r'\d{1,4}[/\-\.]\d{1,2}[/\-\.]\d{2,4}', regex=True
        ).sum()
        abbr_month = sample.str.contains(
            r'\b(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec|'
            r'janvier|février|mars|avril|mai|juin|juillet|août|septembre|octobre|novembre|décembre)\b',
            case=False, regex=True
        ).sum()
        if date_pattern > len(sample) * 0.4 or abbr_month > len(sample) * 0.4:
            return True
    return False

# ============================================================
# FORMATS PANDAS FALLBACK
# ============================================================
_FALLBACK_FORMATS = [
    '%Y-%m-%d', '%d/%m/%Y', '%m/%d/%Y', '%Y/%m/%d',
    '%d-%m-%Y', '%m-%d-%Y',
    '%d-%b-%Y', '%d/%b/%Y', '%b-%d-%Y', '%b/%d/%Y',
    '%d-%B-%Y', '%d/%B/%Y', '%B-%d-%Y', '%B %d %Y',
    '%d %b %Y', '%d %B %Y', '%b %d, %Y', '%B %d, %Y',
    '%Y%m%d', '%d.%m.%Y', '%m.%d.%Y',
    '%b-%Y', '%B-%Y',
]

def pandas_parse_date(val: str) -> str | None:
    """Tente de parser une date avec pandas (multi-format + normalisation mois étrangers)."""
    if not val or not isinstance(val, str):
        return None
    val = val.strip()
    if not val:
        return None

    # Normaliser mois non-anglais
    normalized = normalize_foreign_months(val)

    for fmt in _FALLBACK_FORMATS:
        try:
            parsed = pd.to_datetime(normalized, format=fmt, errors='raise')
            return parsed.strftime('%Y-%m-%d')
        except Exception:
            continue

    # Dernier recours : pandas infer
    parsed = pd.to_datetime(normalized, errors='coerce')
    if pd.notna(parsed):
        return parsed.strftime('%Y-%m-%d')

    return None

# ============================================================
# LLM BATCH PARSE (optionnel, nécessite un client Gemini)
# ============================================================
_DATE_RE = re.compile(r'^\d{4}-\d{2}-\d{2}$')

def flatten_llm_date_response(mapping: dict, raw_vals: list) -> dict:
    """Normalise n'importe quelle structure JSON retournée par le LLM."""
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
                    iso = v[subk]
                    break
            if iso is None:
                for subv in v.values():
                    if isinstance(subv, str) and _DATE_RE.match(subv):
                        iso = subv
                        break
            flat[k] = iso
        elif isinstance(v, str):
            flat[k] = v if _DATE_RE.match(v) else None
        else:
            flat[k] = None

    # Détecter mapping inversé
    matched = sum(1 for k in flat if k in raw_set)
    if matched < len(raw_vals) * 0.3 and mapping:
        inverted = {}
        for k, v in mapping.items():
            if isinstance(v, str) and v in raw_set:
                inverted[v] = k if _DATE_RE.match(k) else None
        if sum(1 for v in inverted.values() if v) > sum(1 for v in flat.values() if v):
            flat = inverted

    return flat


def llm_parse_dates(llm_client, column_name: str, raw_values: list) -> dict | None:
    """
    Parse des dates via LLM Gemini.
    llm_client doit avoir une méthode .call(prompt, max_tokens) → str JSON
    Retourne {original_val: "YYYY-MM-DD" | None}
    """
    if not raw_values:
        return None

    # Normaliser mois étrangers avant envoi
    norm_map = {normalize_foreign_months(v): v for v in raw_values}
    normalized_vals = list(norm_map.keys())
    example_in = normalized_vals[:3]

    import json
    prompt = f"""You are a date parser. Your ONLY job: convert date strings to ISO format.

INPUT — list of date strings for column "{column_name}":
{json.dumps(normalized_vals, ensure_ascii=False)}

OUTPUT RULES (STRICT):
1. Return a FLAT JSON object (no nesting, no arrays)
2. Each key   = the EXACT original string from the input list
3. Each value = the ISO date string "YYYY-MM-DD", or null if unparseable
4. Every input string must appear as a key — do not skip any
5. No extra keys, no explanations, no markdown

EXAMPLE:
Input:  {json.dumps(example_in, ensure_ascii=False)}
Output: {{"01/mar/2003": "2003-03-01", "15-Jun-1990": "1990-06-15", "1990-07-22": "1990-07-22"}}

Respond with ONLY the JSON object."""

    try:
        result = llm_client.call(prompt, max_tokens=2000)
        if not result:
            return None

        import json as _json
        raw_mapping = _json.loads(result)
        mapping_normalized = flatten_llm_date_response(raw_mapping, normalized_vals)

        # Restaurer clés originales
        final_mapping = {}
        for norm_key, iso_val in mapping_normalized.items():
            original_key = norm_map.get(norm_key, norm_key)
            final_mapping[original_key] = iso_val

        return final_mapping
    except Exception:
        return None


# ============================================================
# PARSE UNE COLONNE ENTIÈRE (LLM + fallback)
# ============================================================
def parse_date_column(series: pd.Series, llm_client=None, verbose=True) -> pd.Series:
    """
    Parse une colonne de dates entière → strings 'YYYY-MM-DD'.
    Stratégie : LLM Gemini (batch) puis fallback pandas valeur par valeur.
    
    Args:
        series      : pd.Series avec les valeurs brutes
        llm_client  : objet avec .call(prompt, max_tokens) ou None
        verbose     : afficher les stats
    
    Returns:
        pd.Series de strings 'YYYY-MM-DD' ou NaN
    """
    import sys

    new_series = pd.Series(np.nan, index=series.index, dtype='object')
    raw_vals = series.dropna().astype(str).str.strip().tolist()
    unique_vals = list(dict.fromkeys(v for v in raw_vals if v))

    if not unique_vals:
        return new_series

    # 1. LLM batch
    llm_mapping = {}
    if llm_client is not None:
        try:
            llm_mapping = llm_parse_dates(llm_client, str(series.name), unique_vals) or {}
            if verbose and llm_mapping:
                ok = sum(1 for v in llm_mapping.values() if v)
                print(f"      🤖 LLM: {ok}/{len(unique_vals)} parsés", file=sys.stderr)
        except Exception:
            llm_mapping = {}

    # 2. Appliquer mapping + fallback pandas
    applied = 0
    fallback = 0
    for idx, raw_val in zip(series.index, series):
        if pd.isna(raw_val):
            continue
        raw_str = str(raw_val).strip()
        if not raw_str:
            continue

        # LLM result ?
        result = llm_mapping.get(raw_str)
        if result and _DATE_RE.match(str(result)):
            new_series.at[idx] = result
            applied += 1
        else:
            # Fallback pandas
            fb = pandas_parse_date(raw_str)
            if fb:
                new_series.at[idx] = fb
                fallback += 1

    total = series.notna().sum()
    success = applied + fallback
    if verbose:
        rate = success / total * 100 if total > 0 else 0
        print(f"      ✅ {series.name}: {success}/{total} parsés "
              f"(LLM={applied}, fallback={fallback}, rate={rate:.0f}%)",
              file=sys.stderr)

    return new_series