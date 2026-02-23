#!/usr/bin/env python3
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

import json
import re
import pandas as pd
import numpy as np


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

if __name__ == '__main__':
    class MockLLM:
        available = False
        def call(self, *a, **k): return None

    # Test 1 : dataset inconnu (scores de jeux vidéo)
    df_games = pd.DataFrame({
        'player_id':   range(1, 11),
        'kill_count':  [12, 8, 5, 200, 15, 9, 11, 7, 6, 10],   # 200 = outlier
        'death_count': [3, 5, 2, 1, 4, 6, 3, 8, -2, 5],        # -2 = suspect
        'score':       [1500, 900, 600, 25000, 1800, 1000, 1300, 700, 800, 1100],
        'level':       [10, 7, 5, 50, 12, 8, 9, 6, 7, 8],
    })

    # Test 2 : météo (température peut être négative)
    df_weather = pd.DataFrame({
        'station_id':   range(1, 11),
        'temperature':  [-15, 22, 35, -5, 18, 42, -20, 30, 500, 25],  # 500 = erreur capteur
        'humidity':     [45, 80, 60, 90, 55, 30, 70, 85, 150, 65],    # 150 = impossible
        'wind_speed':   [10, 25, 15, 30, 12, -5, 20, 18, 22, 16],     # -5 = impossible
    })

    print("=" * 60)
    print("TEST 1 : Dataset inconnu (gaming stats)")
    print("=" * 60)
    v1 = ContextAwareValidator(MockLLM())
    df1, r1 = v1.validate(df_games, filename='player_stats.csv')

    print("=" * 60)
    print("TEST 2 : Météo (températures négatives normales)")
    print("=" * 60)
    v2 = ContextAwareValidator(MockLLM())
    df2, r2 = v2.validate(df_weather, filename='weather_stations.csv')

    print(f"\nGaming flags: {[c for c in df1.columns if c.startswith('FLAG_')]}")
    print(f"Weather flags: {[c for c in df2.columns if c.startswith('FLAG_')]}")