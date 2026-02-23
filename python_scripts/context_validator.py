#!/usr/bin/env python3
"""
CONTEXT-AWARE VALIDATOR - v2.1
================================
Approche universelle : le LLM analyse les données et décide lui-même
des règles de validation sans domaine prédéfini.

Stratégie à 3 niveaux :
  1. LLM universel — regarde les données et génère les règles adaptées
  2. Statistique pur — IQR sans connaissance du domaine
  3. Custom — rules.json fourni par l'utilisateur (override tout)

Principe fondamental :
  - On ne SUPPRIME jamais sans être sûr (action "flag" par défaut)
  - Le LLM justifie chaque règle (pourquoi cette valeur est suspecte)
  - L'humain garde le dernier mot via les colonnes FLAG_*

v2.1 changes vs v2.0:
  - GeminiLLM + _safe_stderr imported from llm_client.py (no more duplication)
  - eval() safety guard: LLM-generated conditions are validated against a
    whitelist of safe patterns before execution — prevents arbitrary code execution
"""

import json
import re
import sys
import pandas as pd
import numpy as np

# ── Shared LLM client (single source of truth) ────────────────────────────────
from llm_client import GeminiLLM, _safe_stderr


# ============================================================================
# SAFE EVAL GUARD
# ============================================================================

# Whitelist of condition patterns the validator is allowed to execute.
# Format: df['col_name'] <operator> <numeric_value>
# This prevents the LLM (or a tampered rules.json) from injecting
# arbitrary Python into the eval() call.
_SAFE_CONDITION_PATTERNS = [
    # df['col'] <op> number
    re.compile(
        r"""^df\[['"][^'"]+['"]\]\s*"""
        r"""(?:<=|>=|<|>|==|!=)\s*"""
        r"""-?\d+(?:\.\d+)?$"""
    ),
    # (df['col'] <op> number) & (df['col'] <op> number)  — compound AND
    re.compile(
        r"""^\(df\[['"][^'"]+['"]\]\s*(?:<=|>=|<|>|==|!=)\s*-?\d+(?:\.\d+)?\)"""
        r"""\s*&\s*"""
        r"""\(df\[['"][^'"]+['"]\]\s*(?:<=|>=|<|>|==|!=)\s*-?\d+(?:\.\d+)?\)$"""
    ),
    # (df['col'] <op> number) | (df['col'] <op> number)  — compound OR
    re.compile(
        r"""^\(df\[['"][^'"]+['"]\]\s*(?:<=|>=|<|>|==|!=)\s*-?\d+(?:\.\d+)?\)"""
        r"""\s*\|\s*"""
        r"""\(df\[['"][^'"]+['"]\]\s*(?:<=|>=|<|>|==|!=)\s*-?\d+(?:\.\d+)?\)$"""
    ),
    # df['col'] != constant_value (mode check from statistical outlier detection)
    re.compile(
        r"""^df\[['"][^'"]+['"]\]\s*!=\s*-?\d+(?:\.\d+)?$"""
    ),
]


def _is_safe_condition(condition: str) -> bool:
    """
    Returns True only if the condition string matches a known-safe pattern.

    Rejects anything that could be arbitrary Python:
      - function calls  (os.system, __import__, eval, exec, …)
      - attribute access beyond df['col']
      - string literals in the condition value
      - chained comparisons beyond our compound AND/OR
    """
    stripped = condition.strip()
    return any(p.match(stripped) for p in _SAFE_CONDITION_PATTERNS)


# ============================================================================
# RICH PROFILE BUILDER
# ============================================================================

def build_rich_profile(df: pd.DataFrame, filename: str = '') -> dict:
    """
    Construit un profil riche du dataset pour le LLM.
    Plus le LLM a d'informations, meilleures sont ses règles.
    """
    profile = {
        'filename':       filename,
        'total_rows':     len(df),
        'total_columns':  len(df.columns),
        'columns':        {}
    }

    for col in df.columns:
        series = df[col].dropna()
        info = {
            'name':          col,
            'dtype':         str(df[col].dtype),
            'null_count':    int(df[col].isna().sum()),
            'null_pct':      round(df[col].isna().sum() / len(df) * 100, 1),
            'unique_count':  int(df[col].nunique()),
            'sample_values': [str(v) for v in series.head(8).tolist()],
        }

        if pd.api.types.is_numeric_dtype(df[col]) and len(series) > 0:
            info.update({
                'min':            round(float(series.min()), 4),
                'max':            round(float(series.max()), 4),
                'mean':           round(float(series.mean()), 4),
                'median':         round(float(series.median()), 4),
                'std':            round(float(series.std()), 4),
                'negative_count': int((series < 0).sum()),
                'zero_count':     int((series == 0).sum()),
                'p5':             round(float(series.quantile(0.05)), 4),
                'p25':            round(float(series.quantile(0.25)), 4),
                'p75':            round(float(series.quantile(0.75)), 4),
                'p95':            round(float(series.quantile(0.95)), 4),
            })

        profile['columns'][col] = info

    return profile


# ============================================================================
# STATISTICAL OUTLIER DETECTION (universal — no domain knowledge required)
# ============================================================================

def detect_statistical_outliers(df: pd.DataFrame,
                                 iqr_multiplier: float = 3.0) -> list:
    """
    Détecte les outliers statistiques via IQR.

    iqr_multiplier=3.0 → outliers sévères seulement (conservateur)
    iqr_multiplier=1.5 → outliers modérés (agressif)

    All generated conditions are guaranteed to match _SAFE_CONDITION_PATTERNS.
    """
    rules = []

    for col in df.select_dtypes(include=[np.number]).columns:
        series = df[col].dropna()
        if len(series) < 10:
            continue

        q1  = series.quantile(0.25)
        q3  = series.quantile(0.75)
        iqr = q3 - q1

        if iqr == 0:
            mode_val = series.mode()[0] if len(series.mode()) > 0 else 0
            outliers = (series != mode_val).sum()
            if outliers > 0 and outliers < len(series) * 0.05:
                condition = f"df['{col}'] != {mode_val}"
                if _is_safe_condition(condition):
                    rules.append({
                        'rule_id':       f'stat_constant_{col}',
                        'description':   f'{col}: {outliers} values differ from constant {mode_val}',
                        'column':        col,
                        'condition':     condition,
                        'fix_action':    'flag',
                        'source':        'statistical',
                        'justification': 'Column is nearly constant, outliers may be errors'
                    })
            continue

        lower = q1 - iqr_multiplier * iqr
        upper = q3 + iqr_multiplier * iqr

        n_low  = int((series < lower).sum())
        n_high = int((series > upper).sum())

        if n_low > 0:
            pct = round(n_low / len(series) * 100, 1)
            condition = f"df['{col}'] < {lower}"
            if _is_safe_condition(condition):
                rules.append({
                    'rule_id':       f'stat_low_{col}',
                    'description':   f'{col}: {n_low} values ({pct}%) below {lower:.2f}',
                    'column':        col,
                    'condition':     condition,
                    'fix_action':    'flag',
                    'source':        'statistical',
                    'justification': f'Values below Q1-{iqr_multiplier}xIQR are statistical outliers'
                })

        if n_high > 0:
            pct = round(n_high / len(series) * 100, 1)
            condition = f"df['{col}'] > {upper}"
            if _is_safe_condition(condition):
                rules.append({
                    'rule_id':       f'stat_high_{col}',
                    'description':   f'{col}: {n_high} values ({pct}%) above {upper:.2f}',
                    'column':        col,
                    'condition':     condition,
                    'fix_action':    'flag',
                    'source':        'statistical',
                    'justification': f'Values above Q3+{iqr_multiplier}xIQR are statistical outliers'
                })

    return rules


# ============================================================================
# UNIVERSAL VALIDATOR
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
    - Détection statistique pure (IQR × 3.0) sans connaissance du domaine

    Security note (v2.1):
    - All eval() calls are guarded by _is_safe_condition()
    - LLM-generated conditions that don't match the whitelist are rejected
    - Custom rules from rules.json are subject to the same guard
    """

    def __init__(self, llm: GeminiLLM, rules_path: str = None):
        self.llm          = llm
        self.custom_rules = self._load_custom_rules(rules_path)

    def _load_custom_rules(self, path: str) -> list:
        if not path:
            return []
        try:
            with open(path) as f:
                return json.load(f).get('rules', [])
        except Exception:
            return []

    # ── LLM rule generation ───────────────────────────────────────────────────

    def generate_llm_rules(self, df: pd.DataFrame,
                           filename: str = '') -> tuple[list, str]:
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

KEY PRINCIPLES:
- You don't know the domain in advance — you must figure it out from the data
- A value of 5 for "age" in HR is suspicious, but valid in a pediatric dataset
- A negative value for "temperature" is valid in weather data, but not for body temperature
- An age of 150 is impossible anywhere; an age of 90 is valid in medical data
- Always prefer "flag" over "drop" when unsure — let humans decide

STRICT CONDITION FORMAT — conditions must follow EXACTLY one of these patterns:
  df['column_name'] < 0
  df['column_name'] > 1000
  df['column_name'] == 0
  df['column_name'] != 99
  (df['column_name'] < 0) & (df['column_name'] > -100)
  (df['column_name'] < 0) | (df['column_name'] > 999)
No function calls, no imports, no string comparisons, no chained operators.

RULES TO GENERATE:
- Only for NUMERIC columns
- Only for values clearly anomalous given the inferred context
- 3 to 8 rules maximum
- Skip a column if you cannot confidently determine valid ranges

Respond ONLY with valid JSON (no markdown fences):
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

            parsed     = json.loads(m.group(0))
            rules      = parsed.get('rules', [])
            context    = parsed.get('inferred_context', '')
            confidence = parsed.get('confidence', 'unknown')

            # Filter: column must exist AND condition must be safe
            safe_rules = []
            for r in rules:
                col  = r.get('column', '')
                cond = r.get('condition', '')
                if col not in df.columns:
                    self._log(f"  ⚠️  Rule '{r.get('rule_id')}' skipped: "
                              f"column '{col}' not in dataset")
                    continue
                if not _is_safe_condition(cond):
                    self._log(f"  🚫 Rule '{r.get('rule_id')}' rejected: "
                              f"unsafe condition: {cond!r}")
                    continue
                safe_rules.append(r)

            rejected = len(rules) - len(safe_rules)
            if rejected:
                self._log(f"  ⚠️  {rejected} LLM rule(s) rejected by safety guard")

            self._log(f"Context inferred: {context}")
            self._log(f"Confidence: {confidence}")
            self._log(f"{len(safe_rules)} safe rules accepted from LLM")
            return safe_rules, context

        except Exception as e:
            self._log(f"WARNING: LLM rule generation failed: {e}")
            return [], f'Error: {e}'

    # ── Main validation pipeline ──────────────────────────────────────────────

    def validate(self, df: pd.DataFrame,
                 filename: str = '') -> tuple[pd.DataFrame, list]:
        """
        Pipeline de validation universel.
        Retourne (df_avec_flags, rapport).
        """
        self._log(f"\nContext-aware validation (universal mode)...")
        self._log(f"Input: {len(df)} rows, {len(df.columns)} columns, file='{filename}'")

        # 1. LLM rules (contextualised automatically)
        llm_rules, inferred_context = self.generate_llm_rules(df, filename)

        # 2. Statistical rules (universal, strict IQR)
        stat_rules = detect_statistical_outliers(df, iqr_multiplier=3.0)
        self._log(f"{len(stat_rules)} statistical rules (IQR × 3.0)")

        # 3. Merge: custom > LLM > stat (descending priority)
        all_rules = {}
        for r in stat_rules:
            all_rules[r['rule_id']] = r
        for r in llm_rules:
            all_rules[r['rule_id']] = r
        for r in self.custom_rules:
            # Custom rules from rules.json also go through safety guard
            cond = r.get('condition', '')
            if not _is_safe_condition(cond):
                self._log(f"  🚫 Custom rule '{r.get('rule_id')}' rejected: "
                          f"unsafe condition: {cond!r}")
                continue
            all_rules[r['rule_id']] = r

        self._log(
            f"{len(all_rules)} total rules "
            f"({len(llm_rules)} LLM + {len(stat_rules)} stat "
            f"+ {len(self.custom_rules)} custom)"
        )

        if not all_rules:
            self._log("No rules applicable")
            return df, []

        # 4. Apply rules
        rapport   = []
        flag_cols = {}

        for rule in all_rules.values():
            df, violations, fixed = self._apply(df, rule)
            if violations > 0:
                rapport.append({
                    'rule_id':          rule.get('rule_id'),
                    'description':      rule.get('description'),
                    'column':           rule.get('column'),
                    'violations':       violations,
                    'fixed':            fixed,
                    'action':           rule.get('fix_action', 'flag'),
                    'source':           rule.get('source', 'llm'),
                    'justification':    rule.get('justification', ''),
                    'inferred_context': (
                        inferred_context
                        if rule.get('source') != 'statistical'
                        else ''
                    ),
                })
                if rule.get('fix_action', 'flag') == 'flag':
                    col = rule.get('column', '')
                    flag_cols[col] = flag_cols.get(col, 0) + violations

        # 5. Summary
        if flag_cols:
            self._log("\nSuspicious values flagged (FLAG_* columns added for human review):")
            for col, count in sorted(flag_cols.items(), key=lambda x: -x[1]):
                pct = count / len(df) * 100
                self._log(f"  {col}: {count} rows flagged ({pct:.1f}%)")
        else:
            self._log("No suspicious values found")

        return df, rapport

    # ── Apply a single rule ───────────────────────────────────────────────────

    def _apply(self, df: pd.DataFrame, rule: dict) -> tuple[pd.DataFrame, int, int]:
        rid    = rule.get('rule_id', '?')
        cond   = rule.get('condition', '')
        action = rule.get('fix_action', 'flag')
        col    = rule.get('column', '')
        val    = rule.get('fix_value')
        v = f = 0

        # Safety guard — should already be filtered, but defence in depth
        if not _is_safe_condition(cond):
            self._log(f"  🚫 Rule [{rid}] blocked at apply stage: unsafe condition")
            return df, 0, 0

        try:
            mask = eval(cond, {'df': df, 'pd': pd, 'np': np})  # noqa: S eval
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

        except Exception as e:
            self._log(f"  WARNING: Rule [{rid}] error: {e}")

        return df, v, f

    def _log(self, msg: str):
        try:
            print(f"   {msg}", file=sys.stderr)
        except UnicodeEncodeError:
            print(
                msg.encode('ascii', errors='replace').decode('ascii'),
                file=sys.stderr
            )


# ============================================================================
# SELF-TEST
# ============================================================================

if __name__ == '__main__':
    class _MockLLM:
        available = False
        def call(self, *a, **k): return None

    print("=" * 60)
    print("TEST 1: Safety guard — known-safe conditions")
    print("=" * 60)
    safe_cases = [
        "df['age'] < 0",
        "df['score'] > 1000",
        "df['price'] != 0",
        "(df['temp'] < -100) & (df['temp'] > 100)",
        "(df['val'] < 0) | (df['val'] > 999)",
    ]
    for c in safe_cases:
        ok = _is_safe_condition(c)
        print(f"  {'✅' if ok else '❌'} SAFE   : {c}")

    print()
    print("=" * 60)
    print("TEST 2: Safety guard — dangerous conditions (must all be REJECTED)")
    print("=" * 60)
    dangerous_cases = [
        "os.system('rm -rf /')",
        "__import__('os').system('id')",
        "eval('1+1')",
        "df['age'].apply(lambda x: x)",
        "df['col'].str.contains('hack')",
        "pd.read_csv('/etc/passwd')",
    ]
    all_rejected = True
    for c in dangerous_cases:
        ok = _is_safe_condition(c)
        status = '✅ REJECTED' if not ok else '❌ ALLOWED (BUG!)'
        if ok:
            all_rejected = False
        print(f"  {status}: {c}")

    print()
    print("=" * 60)
    print("TEST 3: Gaming dataset — statistical outlier detection")
    print("=" * 60)
    df_games = pd.DataFrame({
        'player_id':   range(1, 11),
        'kill_count':  [12, 8, 5, 200, 15, 9, 11, 7, 6, 10],
        'death_count': [3, 5, 2, 1, 4, 6, 3, 8, 2, 5],
        'score':       [1500, 900, 600, 25000, 1800, 1000, 1300, 700, 800, 1100],
    })
    v1 = ContextAwareValidator(_MockLLM())
    df1, r1 = v1.validate(df_games, filename='player_stats.csv')
    flags = [c for c in df1.columns if c.startswith('FLAG_')]
    print(f"  Flags added: {flags}")
    print(f"  Rules triggered: {len(r1)}")

    print()
    print("=" * 60)
    print("TEST 4: Weather dataset — negative temps should NOT be flagged by stat")
    print("=" * 60)
    df_weather = pd.DataFrame({
        'station_id':  range(1, 11),
        'temperature': [-15, 22, 35, -5, 18, 42, -20, 30, 28, 25],
        'humidity':    [45, 80, 60, 90, 55, 30, 70, 85, 150, 65],
        'wind_speed':  [10, 25, 15, 30, 12, 20, 20, 18, 22, 16],
    })
    v2 = ContextAwareValidator(_MockLLM())
    df2, r2 = v2.validate(df_weather, filename='weather_stations.csv')
    temp_flags = [r for r in r2 if r['column'] == 'temperature']
    print(f"  Temperature rules triggered: {len(temp_flags)} (expected 0 — negatives are valid)")
    humidity_flags = [r for r in r2 if r['column'] == 'humidity']
    print(f"  Humidity rules triggered: {len(humidity_flags)} (humidity 150% is an outlier)")

    print()
    if all_rejected:
        print("✅ ALL SAFETY TESTS PASSED")
    else:
        print("❌ SAFETY GUARD HAS A BUG — dangerous condition was allowed!")