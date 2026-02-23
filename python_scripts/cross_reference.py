#!/usr/bin/env python3
"""
CROSS REFERENCE ENGINE - v1.1
==============================
Fixes v1.1:
  - Fix 1: Colonnes de référence (SKU, Stock...) exclues du LLM Enricher
  - Fix 2: Recalcul générique des colonnes dérivées (Total = Qty x Price) via LLM
  - Fix 3: Validator moins agressif (interdit les rules sur colonnes textuelles)

Usage:
  python3 cross_reference.py main.csv --output output/
  python3 cross_reference.py main.csv ref1.csv ref2.csv --output output/
  python3 cross_reference.py main.csv ref1.csv --rules rules.json --output output/
"""

import os
import sys
import json
import csv
import argparse
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
# HELPERS
# ============================================================================

def load_csv(path, llm=None):
    """Charge un CSV et normalise automatiquement les colonnes de dates."""
    df = None
    for enc in ['utf-8', 'latin-1', 'iso-8859-1', 'cp1252']:
        try:
            df = pd.read_csv(path, encoding=enc, on_bad_lines='skip')
            _safe_stderr(f"   ✅ Chargé: {os.path.basename(path)} ({len(df)} lignes, encoding={enc})")
            break
        except UnicodeDecodeError:
            continue
        except Exception as e:
            _safe_stderr(f"   ❌ Erreur: {e}")
            return None

    if df is None:
        _safe_stderr(f"   ❌ Impossible de charger: {path}")
        return None

    # Nettoyage global des strings
    for col in df.columns:
        if df[col].dtype == 'object':
            df[col] = df[col].astype(str).str.strip()
            df[col] = df[col].replace(
                ['nan', 'NaN', 'None', '', 'null', 'NULL', 'N/A', 'n/a', 'NA'],
                np.nan
            )

    # Normaliser les colonnes de dates si date_utils est disponible
    if DATE_UTILS_AVAILABLE:
        date_cols_found = [c for c in df.columns if is_date_column(df[c])]
        if date_cols_found:
            _safe_stderr(f"   📅 Colonnes dates détectées: {date_cols_found}")
            for col in date_cols_found:
                _safe_stderr(f"   📅 Parsing: {col}...")
                df[col] = parse_date_column(df[col], llm_client=llm, verbose=True)

    return df

def create_profile(df, max_samples=5):
    profile = {"total_rows": len(df), "total_columns": len(df.columns), "columns": {}}
    for col in df.columns:
        info = {
            "dtype": str(df[col].dtype),
            "null_count": int(df[col].isna().sum()),
            "null_pct": round(float(df[col].isna().sum() / len(df) * 100), 1) if len(df) > 0 else 0,
            "unique_count": int(df[col].nunique()),
            "sample_values": [str(v) for v in df[col].dropna().head(max_samples).tolist()]
        }
        if pd.api.types.is_numeric_dtype(df[col]):
            non_null = df[col].dropna()
            if len(non_null) > 0:
                info["min"]  = float(non_null.min())
                info["max"]  = float(non_null.max())
                info["mean"] = round(float(non_null.mean()), 2)
        profile["columns"][col] = info
    return profile

# ============================================================================
# 1. CROSS REFERENCE ENGINE
# ============================================================================

class CrossReferenceEngine:
    def __init__(self, llm: GeminiLLM):
        self.llm = llm
        self.ref_columns = set()

    def find_join_keys_exact(self, df_main, df_ref):
        common = list(set(df_main.columns) & set(df_ref.columns))
        good_keys = []
        for col in common:
            main_vals = set(df_main[col].dropna().astype(str).str.strip().str.lower())
            ref_vals  = set(df_ref[col].dropna().astype(str).str.strip().str.lower())
            overlap = len(main_vals & ref_vals)
            if overlap > 0:
                good_keys.append((col, overlap / max(len(main_vals), 1)))
        good_keys.sort(key=lambda x: x[1], reverse=True)
        return [k[0] for k in good_keys]

    def find_join_keys_llm(self, df_main, df_ref):
        if not self.llm.available:
            return []
        prompt = f"""Find JOIN keys between these two datasets (even if column names differ).

Main: {json.dumps(create_profile(df_main), indent=2)}
Reference: {json.dumps(create_profile(df_ref), indent=2)}

Respond ONLY valid JSON:
{{"join_pairs": [{{"main_column": "...", "ref_column": "...", "confidence": 0.0}}]}}
If none found: {{"join_pairs": []}}"""
        result = self.llm.call(prompt, max_tokens=600)
        if not result:
            return []
        try:
            pairs = json.loads(result).get("join_pairs", [])
            return [p for p in pairs if p.get("confidence", 0) >= 0.6]
        except:
            return []

    def enrich(self, df_main, df_ref, ref_name="reference"):
        rapport = {"ref_file": ref_name, "method": None, "join_keys": [],
                   "columns_added": [], "rows_enriched": 0}
        _safe_stderr(f"\n   🔗 Cross-reference avec: {ref_name}")
        exact_keys = self.find_join_keys_exact(df_main, df_ref)
        if exact_keys:
            _safe_stderr(f"      ✅ Clés exactes: {exact_keys}")
            rapport["method"] = "exact"
            rapport["join_keys"] = exact_keys
            df_main = self._merge(df_main, df_ref, exact_keys, exact_keys, rapport)
        else:
            _safe_stderr(f"      🤖 Pas de clé exacte, consultation LLM...")
            llm_pairs = self.find_join_keys_llm(df_main, df_ref)
            if llm_pairs:
                main_keys = [p["main_column"] for p in llm_pairs]
                ref_keys  = [p["ref_column"]  for p in llm_pairs]
                _safe_stderr(f"      ✅ Clés LLM: {list(zip(main_keys, ref_keys))}")
                rapport["method"] = "llm"
                df_ref_r = df_ref.rename(columns={ref_keys[i]: main_keys[i] for i in range(len(main_keys))})
                df_main = self._merge(df_main, df_ref_r, main_keys, main_keys, rapport)
            else:
                _safe_stderr(f"      ❌ Aucune clé trouvée, référence ignorée")
                rapport["method"] = "none"
        return df_main, rapport

    def _merge(self, df_main, df_ref, main_keys, ref_keys, rapport):
        cols_to_add  = [c for c in df_ref.columns if c not in df_main.columns and c not in ref_keys]
        cols_to_fill = [c for c in df_ref.columns if c in df_main.columns and c not in ref_keys]
        if not cols_to_add and not cols_to_fill:
            _safe_stderr(f"      ℹ️  Aucune colonne à enrichir")
            return df_main
        df_m = df_main.copy()
        df_r = df_ref.copy()
        for key in main_keys:
            df_m[f"__k_{key}"] = df_m[key].astype(str).str.strip().str.lower()
        for key in ref_keys:
            df_r[f"__k_{key}"] = df_r[key].astype(str).str.strip().str.lower()
        merge_keys = [f"__k_{k}" for k in main_keys]
        ref_sub = df_r[merge_keys + cols_to_add + cols_to_fill].copy().add_suffix("_REF")
        ref_sub = ref_sub.rename(columns={f"__k_{k}_REF": f"__k_{k}" for k in main_keys})
        df_merged = df_m.merge(ref_sub, on=merge_keys, how="left")
        for col in cols_to_fill:
            ref_col = f"{col}_REF"
            if ref_col in df_merged.columns:
                nb = df_merged[col].isna().sum()
                df_merged[col] = df_merged[col].combine_first(df_merged[ref_col])
                filled = nb - df_merged[col].isna().sum()
                if filled > 0:
                    _safe_stderr(f"      📥 {col}: {filled} NULL remplis")
                    rapport["rows_enriched"] += filled
                df_merged.drop(columns=[ref_col], inplace=True)
                self.ref_columns.add(col)
        for col in cols_to_add:
            ref_col = f"{col}_REF"
            if ref_col in df_merged.columns:
                df_merged[col] = df_merged[ref_col]
                df_merged.drop(columns=[ref_col], inplace=True)
                _safe_stderr(f"      ➕ Colonne ajoutée: {col}")
                rapport["columns_added"].append(col)
                self.ref_columns.add(col)
        df_merged.drop(columns=[c for c in df_merged.columns if c.startswith("__k_")], inplace=True)
        return df_merged

# ============================================================================
# 2. VALIDATOR

# ============================================================================

# ============================================================================
# 2. CONTEXT-AWARE VALIDATOR v2 (universal — no hardcoded domains)
# ============================================================================
"""
CONTEXT-AWARE VALIDATOR v2.0
==============================
Approche universelle : le LLM analyse les données et décide lui-même
des règles de validation sans domaine prédéfini.

Stratégie à 3 niveaux :
  1. LLM universel — regarde les données et génère les règles adaptées
  2. Statistique pur — IQR + zscore sans connaissance du domaine
  3. Custom — rules.json fourni par l'utilisateur (override tout)

Principe fondamental :
  - On ne SUPPRIME jamais sans être sûr (action "flag" par défaut)
  - Le LLM justifie chaque règle (pourquoi cette valeur est suspecte)
  - L'humain garde le dernier mot via les colonnes FLAG_*
"""



# ============================================================================
# PROFIL ENRICHI — donne le maximum de contexte au LLM
# ============================================================================

def build_rich_profile(df: pd.DataFrame, filename: str = '') -> dict:
    """
    Construit un profil riche du dataset pour le LLM.
    Plus le LLM a d'informations, meilleures sont ses règles.
    """
    profile = {
        'filename': filename,
        'total_rows': len(df),
        'total_columns': len(df.columns),
        'columns': {}
    }

    for col in df.columns:
        series = df[col].dropna()
        info = {
            'name': col,
            'dtype': str(df[col].dtype),
            'null_count': int(df[col].isna().sum()),
            'null_pct': round(df[col].isna().sum() / len(df) * 100, 1),
            'unique_count': int(df[col].nunique()),
            'sample_values': [str(v) for v in series.head(8).tolist()],
        }

        if pd.api.types.is_numeric_dtype(df[col]) and len(series) > 0:
            info.update({
                'min':    round(float(series.min()), 4),
                'max':    round(float(series.max()), 4),
                'mean':   round(float(series.mean()), 4),
                'median': round(float(series.median()), 4),
                'std':    round(float(series.std()), 4),
                'negative_count': int((series < 0).sum()),
                'zero_count':     int((series == 0).sum()),
                # Percentiles pour donner la distribution
                'p5':   round(float(series.quantile(0.05)), 4),
                'p25':  round(float(series.quantile(0.25)), 4),
                'p75':  round(float(series.quantile(0.75)), 4),
                'p95':  round(float(series.quantile(0.95)), 4),
            })

        profile['columns'][col] = info

    return profile


# ============================================================================
# DÉTECTION STATISTIQUE UNIVERSELLE (sans connaissance du domaine)
# ============================================================================

def detect_statistical_outliers(df: pd.DataFrame, iqr_multiplier: float = 3.0) -> list:
    """
    Détecte les outliers statistiques via IQR.
    Universel — fonctionne pour n'importe quel type de données.
    
    iqr_multiplier=3.0 = outliers sévères seulement (conservateur)
    iqr_multiplier=1.5 = outliers modérés (agressif)
    """
    rules = []

    for col in df.select_dtypes(include=[np.number]).columns:
        series = df[col].dropna()
        if len(series) < 10:
            continue

        q1   = series.quantile(0.25)
        q3   = series.quantile(0.75)
        iqr  = q3 - q1

        if iqr == 0:
            # Colonne quasi-constante — outliers = valeurs différentes
            mode_val = series.mode()[0] if len(series.mode()) > 0 else 0
            outliers = (series != mode_val).sum()
            if outliers > 0 and outliers < len(series) * 0.05:
                rules.append({
                    'rule_id':     f'stat_constant_{col}',
                    'description': f'{col}: {outliers} values differ from constant {mode_val}',
                    'column':      col,
                    'condition':   f"df['{col}'] != {mode_val}",
                    'fix_action':  'flag',
                    'source':      'statistical',
                    'justification': 'Column is nearly constant, outliers may be errors'
                })
            continue

        lower = q1 - iqr_multiplier * iqr
        upper = q3 + iqr_multiplier * iqr

        n_low  = int((series < lower).sum())
        n_high = int((series > upper).sum())

        if n_low > 0:
            pct = round(n_low / len(series) * 100, 1)
            rules.append({
                'rule_id':     f'stat_low_{col}',
                'description': f'{col}: {n_low} values ({pct}%) below {lower:.2f} (Q1 - {iqr_multiplier}×IQR)',
                'column':      col,
                'condition':   f"df['{col}'] < {lower}",
                'fix_action':  'flag',
                'source':      'statistical',
                'justification': f'Values below Q1-{iqr_multiplier}xIQR are statistical outliers'
            })

        if n_high > 0:
            pct = round(n_high / len(series) * 100, 1)
            rules.append({
                'rule_id':     f'stat_high_{col}',
                'description': f'{col}: {n_high} values ({pct}%) above {upper:.2f} (Q3 + {iqr_multiplier}×IQR)',
                'column':      col,
                'condition':   f"df['{col}'] > {upper}",
                'fix_action':  'flag',
                'source':      'statistical',
                'justification': f'Values above Q3+{iqr_multiplier}xIQR are statistical outliers'
            })

    return rules


# ============================================================================
# VALIDATEUR UNIVERSEL
# ============================================================================

class ContextAwareValidator:
    """
    Validateur universel : s'adapte à N'IMPORTE quel type de données.
    
    Le LLM reçoit un profil complet et décide lui-même :
    - Quel est le contexte probable de ces données
    - Quelles valeurs sont suspectes dans CE contexte
    - Quelle action prendre (flag, abs, null, drop)
    - Pourquoi (justification incluse dans le rapport)
    
    Si le LLM n'est pas disponible :
    - Détection statistique pure (IQR) sans connaissance du domaine
    """

    def __init__(self, llm, rules_path=None):
        self.llm = llm
        self.custom_rules = self._load_custom_rules(rules_path)

    def _load_custom_rules(self, path):
        if not path:
            return []
        try:
            with open(path) as f:
                return json.load(f).get('rules', [])
        except Exception:
            return []

    # ── LLM universel ─────────────────────────────────────────────────────

    def generate_llm_rules(self, df: pd.DataFrame, filename: str = '') -> tuple:
        """
        Le LLM analyse le dataset et génère des règles adaptées.
        Retourne (rules, context_description).
        """
        if not self.llm.available:
            return [], 'LLM unavailable'

        profile = build_rich_profile(df, filename)

        prompt = f"""You are a data quality expert. Analyze this dataset and generate validation rules.

Dataset profile:
{json.dumps(profile, indent=2)}

YOUR TASK:
1. First, infer what this dataset is about from column names, filename, and sample values
2. Based on that understanding, generate validation rules for suspicious values
3. For each rule, explain WHY the value is suspicious in this specific context

KEY PRINCIPLE:
- You don't know the domain in advance — you must figure it out from the data
- A value of 5 for "age" in HR is suspicious, but valid in a pediatric dataset
- A negative value for "temperature" is valid in weather data, but not for body temperature
- An age of 150 is impossible anywhere; an age of 90 is valid in medical data
- Always prefer "flag" over "drop" when unsure — let humans decide

RULES TO GENERATE:
- Only for NUMERIC columns
- Only for values that are clearly anomalous given the inferred context
- 3 to 8 rules maximum
- Skip a column if you can't confidently determine valid ranges from context

Respond ONLY with valid JSON:
{{
  "inferred_context": "What you think this dataset is about and why",
  "confidence": "high|medium|low",
  "rules": [
    {{
      "rule_id": "short_unique_id",
      "description": "Human-readable description",
      "column": "exact_column_name",
      "condition": "df['column_name'] < 0",
      "fix_action": "flag",
      "justification": "Why this is anomalous in the inferred context"
    }}
  ]
}}"""

        try:
            result = self.llm.call(prompt, max_tokens=2000)
            if not result:
                return [], 'LLM returned nothing'

            m = re.search(r'\{.*\}', result, re.DOTALL)
            if not m:
                return [], 'LLM response not parseable'

            parsed = json.loads(m.group(0))
            rules = parsed.get('rules', [])
            context = parsed.get('inferred_context', '')
            confidence = parsed.get('confidence', 'unknown')

            # Filtrer les règles sur des colonnes inexistantes
            rules = [r for r in rules if r.get('column', '') in df.columns]

            self._log(f"Context inferred: {context}")
            self._log(f"Confidence: {confidence}")
            self._log(f"{len(rules)} rules generated by LLM")
            return rules, context

        except Exception as e:
            self._log(f"WARNING: LLM rule generation failed: {e}")
            return [], f'Error: {e}'

    # ── Pipeline principal ─────────────────────────────────────────────────

    def validate(self, df: pd.DataFrame, filename: str = '') -> tuple:
        """
        Pipeline de validation universel.
        Retourne (df_avec_flags, rapport).
        """
        self._log(f"\nContext-aware validation (universal mode)...")
        self._log(f"Input: {len(df)} rows, {len(df.columns)} columns, file='{filename}'")

        # 1. Règles LLM (contextualisées automatiquement)
        llm_rules, inferred_context = self.generate_llm_rules(df, filename)

        # 2. Règles statistiques (universelles, IQR strict)
        stat_rules = detect_statistical_outliers(df, iqr_multiplier=3.0)
        self._log(f"{len(stat_rules)} statistical rules (IQR × 3.0)")

        # 3. Merger : custom > LLM > stat (priorité décroissante)
        all_rules = {}
        for r in stat_rules:
            all_rules[r['rule_id']] = r
        for r in llm_rules:
            all_rules[r['rule_id']] = r
        for r in self.custom_rules:
            all_rules[r['rule_id']] = r

        self._log(f"{len(all_rules)} total rules "
                  f"({len(llm_rules)} LLM + {len(stat_rules)} stat + {len(self.custom_rules)} custom)")

        if not all_rules:
            self._log("No rules applicable")
            return df, []

        # 4. Appliquer les règles
        rapport = []
        flag_cols = {}

        for rule in all_rules.values():
            df, violations, fixed = self._apply(df, rule)
            if violations > 0:
                rapport.append({
                    'rule_id':        rule.get('rule_id'),
                    'description':    rule.get('description'),
                    'column':         rule.get('column'),
                    'violations':     violations,
                    'fixed':          fixed,
                    'action':         rule.get('fix_action', 'flag'),
                    'source':         rule.get('source', 'llm'),
                    'justification':  rule.get('justification', ''),
                    'inferred_context': inferred_context if rule.get('source') != 'statistical' else '',
                })
                if rule.get('fix_action', 'flag') == 'flag':
                    col = rule.get('column', '')
                    flag_cols[col] = flag_cols.get(col, 0) + violations

        # 5. Résumé lisible
        if flag_cols:
            self._log(f"\nSuspicious values flagged (FLAG_* columns added for human review):")
            for col, count in sorted(flag_cols.items(), key=lambda x: -x[1]):
                pct = count / len(df) * 100
                self._log(f"  {col}: {count} rows flagged ({pct:.1f}%)")
        else:
            self._log("No suspicious values found")

        return df, rapport

    # ── Appliquer une règle ───────────────────────────────────────────────

    def _apply(self, df: pd.DataFrame, rule: dict) -> tuple:
        rid    = rule.get('rule_id', '?')
        cond   = rule.get('condition', '')
        action = rule.get('fix_action', 'flag')
        col    = rule.get('column', '')
        val    = rule.get('fix_value')
        v = f = 0

        try:
            mask = eval(cond, {'df': df, 'pd': pd, 'np': np})
            if not isinstance(mask, pd.Series):
                return df, 0, 0
            v = int(mask.sum())
            if v == 0:
                return df, 0, 0

            desc = rule.get('description', rid)
            self._log(f"  [{action.upper()}] {desc}: {v} row(s)")

            if action == 'abs' and col in df.columns:
                df.loc[mask, col] = df.loc[mask, col].abs()
                f = v
            elif action == 'drop':
                df = df[~mask].copy()
                f = v
            elif action == 'null' and col in df.columns:
                df.loc[mask, col] = np.nan
                f = v
            elif action == 'set' and col in df.columns and val is not None:
                df.loc[mask, col] = val
                f = v
            elif action == 'flag':
                flag_col = f'FLAG_{col}'
                if flag_col not in df.columns:
                    df[flag_col] = False
                df.loc[mask, flag_col] = True
                # flag = 0 fixes (humain décide)
        except Exception as e:
            self._log(f"  WARNING: Rule [{rid}] error: {e}")

        return df, v, f

    def _log(self, msg: str):
        import sys
        try:
            print(f"   {msg}", file=sys.stderr)
        except UnicodeEncodeError:
            print(msg.encode('ascii', errors='replace').decode('ascii'), file=sys.stderr)


# ============================================================================
# TEST
# ============================================================================



# ============================================================================

class DerivedColumnsRecalculator:
    def __init__(self, llm: GeminiLLM):
        self.llm = llm

    def detect_formulas(self, df):
        if not self.llm.available:
            return []
        numeric_cols = df.select_dtypes(include=[np.number]).columns.tolist()
        if len(numeric_cols) < 2:
            return []
        profile = {"total_rows": len(df), "numeric_columns": {}}
        for col in numeric_cols:
            non_null = df[col].dropna()
            if len(non_null) > 0:
                profile["numeric_columns"][col] = {
                    "min":  round(float(non_null.min()), 4),
                    "max":  round(float(non_null.max()), 4),
                    "mean": round(float(non_null.mean()), 4),
                    "samples": [round(float(v), 4) for v in non_null.head(5).tolist()]
                }
        prompt = f"""Detect mathematical relationships between numeric columns (derived columns).

Numeric profile:
{json.dumps(profile, indent=2)}

Look for: multiplication, subtraction, addition, division between columns.
Verify using sample values.

Respond ONLY valid JSON:
{{
  "derived_columns": [
    {{
      "target": "column_to_recalculate",
      "formula": "df['A'] * df['B']",
      "formula_readable": "A x B",
      "confidence": 0.0
    }}
  ]
}}

Only confidence >= 0.85. If none: {{"derived_columns": []}}
formula must be valid pandas."""
        result = self.llm.call(prompt, max_tokens=600)
        if not result:
            return []
        try:
            formulas = json.loads(result).get("derived_columns", [])
            return [f for f in formulas if f.get("confidence", 0) >= 0.85]
        except:
            return []

    def recalculate(self, df):
        _safe_stderr(f"\n🔢 Détection des colonnes dérivées...")
        formulas = self.detect_formulas(df)
        if not formulas:
            _safe_stderr("   ℹ️  Aucune colonne dérivée détectée")
            return df, []
        _safe_stderr(f"   🤖 {len(formulas)} formule(s) détectée(s)")
        rapport = []
        for fi in formulas:
            target   = fi.get("target")
            formula  = fi.get("formula")
            readable = fi.get("formula_readable", formula)
            if not target or not formula or target not in df.columns:
                continue
            _safe_stderr(f"\n   📐 {target} = {readable}")
            try:
                expected = eval(formula, {"df": df, "pd": pd, "np": np})
                if not isinstance(expected, pd.Series):
                    continue
                valid = df[target].notna() & expected.notna()
                if valid.sum() == 0:
                    continue
                match_pct = (abs(df.loc[valid, target] - expected[valid]) < 0.05).sum() / valid.sum()
                if match_pct < 0.70:
                    _safe_stderr(f"      ⚠️  {match_pct*100:.0f}% de correspondance, ignorée")
                    continue
                _safe_stderr(f"      ✅ Formule validée ({match_pct*100:.0f}% match)")
                incoherent = valid & (abs(df[target] - expected) > 0.05)
                to_fix = incoherent | df[target].isna()
                if to_fix.sum() == 0:
                    continue
                df.loc[to_fix, target] = expected[to_fix]
                count = int(to_fix.sum())
                _safe_stderr(f"      🔢 {count} valeurs recalculées")
                rapport.append({"target": target, "formula": readable, "recalculated": count})
            except Exception as e:
                _safe_stderr(f"      ❌ Erreur: {e}")
        return df, rapport

# ============================================================================
# 4. LLM ENRICHER
# ============================================================================

class LLMEnricher:
    IDENTITY_KEYWORDS = ['id', 'email', 'phone', 'address', 'user', 'customer']

    def __init__(self, llm: GeminiLLM):
        self.llm = llm

    def enrich(self, df, ref_columns=None, max_rows=50):
        if not self.llm.available:
            _safe_stderr("   ℹ️  LLM Enricher désactivé")
            return df, []
        ref_columns = set(ref_columns or [])
        null_cols = [(c, int(df[c].isna().sum())) for c in df.columns if df[c].isna().sum() > 0]
        if not null_cols:
            _safe_stderr("   ✅ Aucun NULL restant")
            return df, []
        _safe_stderr(f"\n🤖 LLM Enricher — {len(null_cols)} colonnes avec NULL")
        report = []
        for col, null_count in null_cols:
            if col in ref_columns:
                _safe_stderr(f"   🔗 {col}: ignoré (colonne de référence)")
                continue
            if any(kw in col.lower() for kw in self.IDENTITY_KEYWORDS):
                _safe_stderr(f"   🔒 {col}: ignoré (identité)")
                continue
            null_mask = df[col].isna()
            rows_to_enrich = df[null_mask].head(max_rows)
            if len(rows_to_enrich) == 0:
                continue
            _safe_stderr(f"   🔍 {col} ({min(null_count, max_rows)} lignes)")
            context_rows = []
            for idx, row in rows_to_enrich.iterrows():
                ctx = {c: str(v) for c, v in row.items()
                       if c != col and pd.notna(v) and not c.startswith("FLAG_")}
                context_rows.append({"index": int(idx), "context": ctx})
            known = df[~null_mask][col].dropna().head(10).tolist()
            prompt = f"""Predict missing values for column "{col}".
Known values: {known}
Rows to predict:
{json.dumps(context_rows, indent=2)}

Respond ONLY valid JSON:
{{"predictions": [{{"index": 0, "predicted_value": "...", "confidence": 0.0}}]}}
Only confidence >= 0.7."""
            result = self.llm.call(prompt, max_tokens=1000)
            if not result:
                continue
            try:
                predictions = json.loads(result).get("predictions", [])
                applied = 0
                for p in predictions:
                    if p.get("confidence", 0) >= 0.7 and p.get("index") is not None:
                        df.at[p["index"], col] = p["predicted_value"]
                        applied += 1
                _safe_stderr(f"      ✅ {applied}/{len(rows_to_enrich)} prédites")
                report.append({"column": col, "total_null": null_count, "enriched": applied})
            except Exception as e:
                _safe_stderr(f"      ❌ {e}")
        return df, report

# ============================================================================
# 5. IMPUTER
# ============================================================================

class Imputer:
    IDENTITY_KEYWORDS = ['id', 'email', 'phone', 'address', 'name', 'user', 'customer', 'code']

    def impute(self, df):
        _safe_stderr(f"\n🔧 Imputation finale...")
        null_cols = [(c, int(df[c].isna().sum())) for c in df.columns if df[c].isna().sum() > 0]
        if not null_cols:
            _safe_stderr("   ✅ Aucun NULL")
            return df
        for col, count in null_cols:
            if any(kw in col.lower() for kw in self.IDENTITY_KEYWORDS):
                _safe_stderr(f"   🔒 {col}: {count} NULL conservés (identité)")
                continue
            if pd.api.types.is_numeric_dtype(df[col]):
                med = df[col].median()
                if pd.notna(med):
                    df[col] = df[col].fillna(med)
                    _safe_stderr(f"   🔢 {col}: médiane ({med:.2f})")
            else:
                mode = df[col].mode()
                if not mode.empty:
                    df[col] = df[col].fillna(mode[0])
                    _safe_stderr(f"   📝 {col}: mode ('{mode[0]}')")
        return df

# ============================================================================
# EXPORT
# ============================================================================

def export_results(df, input_path, output_dir, rapport):
    os.makedirs(output_dir, exist_ok=True)
    base = os.path.splitext(os.path.basename(input_path))[0]
    out_csv  = os.path.join(output_dir, f"{base}_ENRICHED.csv")
    out_json = os.path.join(output_dir, f"{base}_REPORT.json")
    df.to_csv(out_csv, index=False, quoting=csv.QUOTE_MINIMAL)
    with open(out_json, 'w') as f:
        json.dump(rapport, f, indent=2, default=str)
    _safe_stderr(f"\n   💾 CSV: {out_csv}")
    _safe_stderr(f"   📊 Rapport: {out_json}")
    return out_csv, out_json

# ============================================================================
# MAIN PIPELINE
# ============================================================================

class CrossReferencePipeline:

    def __init__(self, llm, rules_path=None, use_llm_enricher=True):
        self.llm              = llm
        self.cross_ref_engine = CrossReferenceEngine(llm)
        self.validator        = Validator(llm, rules_path)
        self.derived_recalc   = DerivedColumnsRecalculator(llm)
        self.llm_enricher     = LLMEnricher(llm) if use_llm_enricher else None
        self.imputer          = Imputer()

    def run(self, main_file, ref_files, output_dir):
        _safe_stderr(f"\n{'='*60}")
        _safe_stderr(f"🚀 CROSS REFERENCE PIPELINE v2.0")
        _safe_stderr(f"📂 {os.path.basename(main_file)}")
        if ref_files:
            _safe_stderr(f"📚 Refs: {[os.path.basename(r) for r in ref_files]}")
        _safe_stderr(f"{'='*60}")

        # Chargement avec normalisation des dates intégrée
        df = load_csv(main_file, llm=self.llm)
        if df is None:
            return None

        rapport = {
            "main_file": main_file, "ref_files": ref_files,
            "initial_rows": len(df), "initial_cols": len(df.columns),
            "cross_reference": [], "validation": [],
            "derived_columns": [], "enrichment": [],
            "final_rows": 0, "final_cols": 0, "null_remaining": 0
        }

        # 1. Cross Reference
        if ref_files:
            _safe_stderr(f"\n{'─'*40}\n1️⃣  CROSS REFERENCE\n{'─'*40}")
            for ref_file in ref_files:
                df_ref = load_csv(ref_file, llm=self.llm)
                if df_ref is None:
                    continue
                df, ref_r = self.cross_ref_engine.enrich(df, df_ref, os.path.basename(ref_file))
                rapport["cross_reference"].append(ref_r)
        else:
            _safe_stderr(f"\n   ℹ️  Mode simple (pas de références)")

        # 2. Validation
        _safe_stderr(f"\n{'─'*40}\n2️⃣  VALIDATION MÉTIER\n{'─'*40}")
        df, val_r = self.validator.validate(df, filename=os.path.basename(main_file))
        rapport["validation"] = val_r

        # 3. Recalcul colonnes dérivées
        _safe_stderr(f"\n{'─'*40}\n3️⃣  RECALCUL COLONNES DÉRIVÉES\n{'─'*40}")
        df, der_r = self.derived_recalc.recalculate(df)
        rapport["derived_columns"] = der_r

        # 4. LLM Enricher
        if self.llm_enricher and self.llm.available:
            _safe_stderr(f"\n{'─'*40}\n4️⃣  LLM ENRICHER\n{'─'*40}")
            df, enr_r = self.llm_enricher.enrich(
                df, ref_columns=self.cross_ref_engine.ref_columns
            )
            rapport["enrichment"] = enr_r

        # 5. Imputation finale
        _safe_stderr(f"\n{'─'*40}\n5️⃣  IMPUTATION FINALE\n{'─'*40}")
        df = self.imputer.impute(df)

        # 6. Export
        _safe_stderr(f"\n{'─'*40}\n6️⃣  EXPORT\n{'─'*40}")
        rapport.update({"final_rows": len(df), "final_cols": len(df.columns),
                         "null_remaining": int(df.isna().sum().sum())})
        out_csv, out_json = export_results(df, main_file, output_dir, rapport)

        _safe_stderr(f"\n{'='*60}")
        _safe_stderr(f"✅ PIPELINE TERMINÉ")
        _safe_stderr(f"   Lignes  : {rapport['initial_rows']} → {rapport['final_rows']}")
        _safe_stderr(f"   Colonnes: {rapport['initial_cols']} → {rapport['final_cols']}")
        _safe_stderr(f"   NULL restants: {rapport['null_remaining']}")
        _safe_stderr(f"{'='*60}")

        return {"status": "success", "output_csv": out_csv, "output_report": out_json, "rapport": rapport}

# ============================================================================
# CLI
# ============================================================================

def main():
    parser = argparse.ArgumentParser(description="Cross Reference Engine v2.0")
    parser.add_argument("files", nargs="+")
    parser.add_argument("--output", default="cross_ref_output")
    parser.add_argument("--rules", default=None)
    parser.add_argument("--no-llm-enricher", action="store_true")
    args = parser.parse_args()

    for f in args.files:
        if not os.path.exists(f):
            print(json.dumps({"status": "error", "message": f"Introuvable: {f}"}))
            sys.exit(1)

    llm = GeminiLLM()
    pipeline = CrossReferencePipeline(llm, rules_path=args.rules,
                                       use_llm_enricher=not args.no_llm_enricher)
    result = pipeline.run(args.files[0], args.files[1:], args.output)

    if result:
        print(json.dumps({
            "status": "success",
            "output_csv": result["output_csv"],
            "output_report": result["output_report"],
            "final_rows": result["rapport"]["final_rows"],
            "final_cols": result["rapport"]["final_cols"],
            "null_remaining": result["rapport"]["null_remaining"]
        }))
    else:
        print(json.dumps({"status": "error", "message": "Pipeline échoué"}))
        sys.exit(1)

if __name__ == "__main__":
    main()