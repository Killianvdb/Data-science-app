#!/usr/bin/env python3
"""
CROSS REFERENCE ENGINE - v1.2
==============================
v1.2 changes vs v1.1:
  - GeminiLLM + _safe_stderr imported from llm_client.py (no more duplication)
  - ContextAwareValidator + detect_statistical_outliers imported from
    context_validator.py (no more copy-paste duplication)
  - dedup_after_merge now correctly tracked and reported in pipeline rapport
  - build_rich_profile kept local (cross_reference uses create_profile, a
    lighter variant; context_validator uses build_rich_profile internally)

Previous fixes (v1.1):
  - Colonnes de référence (SKU, Stock...) exclues du LLM Enricher
  - Recalcul générique des colonnes dérivées (Total = Qty x Price) via LLM
  - Validator moins agressif (interdit les rules sur colonnes textuelles)

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
import re
import warnings
warnings.filterwarnings('ignore')

# ── Shared modules ─────────────────────────────────────────────────────────────
from llm_client import GeminiLLM, _safe_stderr
from context_validator import ContextAwareValidator, detect_statistical_outliers

# date_utils optional
DATE_UTILS_AVAILABLE = False
try:
    from date_utils import is_date_column, parse_date_column
    DATE_UTILS_AVAILABLE = True
except ImportError:
    pass


# ============================================================================
# HELPERS
# ============================================================================

def load_csv(path: str, llm: GeminiLLM = None) -> pd.DataFrame | None:
    """Charge un CSV et normalise automatiquement les colonnes de dates."""
    df = None
    for enc in ['utf-8', 'latin-1', 'iso-8859-1', 'cp1252']:
        try:
            df = pd.read_csv(path, encoding=enc, on_bad_lines='skip')
            _safe_stderr(
                f"   ✅ Loaded: {os.path.basename(path)} "
                f"({len(df)} rows, encoding={enc})"
            )
            break
        except UnicodeDecodeError:
            continue
        except Exception as e:
            _safe_stderr(f"   ❌ Error: {e}")
            return None

    if df is None:
        _safe_stderr(f"   ❌ Cannot load: {path}")
        return None

    # Global string cleanup
    for col in df.columns:
        if df[col].dtype == 'object':
            df[col] = df[col].astype(str).str.strip()
            df[col] = df[col].replace(
                ['nan', 'NaN', 'None', '', 'null', 'NULL', 'N/A', 'n/a', 'NA'],
                np.nan
            )

    # Normalise date columns if date_utils is available
    if DATE_UTILS_AVAILABLE:
        date_cols_found = [c for c in df.columns if is_date_column(df[c])]
        if date_cols_found:
            _safe_stderr(f"   📅 Date columns detected: {date_cols_found}")
            for col in date_cols_found:
                _safe_stderr(f"   📅 Parsing: {col}...")
                df[col] = parse_date_column(df[col], llm_client=llm, verbose=True)

    return df


def create_profile(df: pd.DataFrame, max_samples: int = 5) -> dict:
    """Profil compact du dataset (utilisé pour les prompts LLM de cross-ref)."""
    profile = {
        "total_rows":    len(df),
        "total_columns": len(df.columns),
        "columns":       {}
    }
    for col in df.columns:
        info = {
            "dtype":         str(df[col].dtype),
            "null_count":    int(df[col].isna().sum()),
            "null_pct":      round(
                float(df[col].isna().sum() / len(df) * 100), 1
            ) if len(df) > 0 else 0,
            "unique_count":  int(df[col].nunique()),
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
        self.llm         = llm
        self.ref_columns = set()

    def find_join_keys_exact(self, df_main: pd.DataFrame,
                              df_ref: pd.DataFrame) -> list:
        common    = list(set(df_main.columns) & set(df_ref.columns))
        good_keys = []
        for col in common:
            main_vals = set(df_main[col].dropna().astype(str).str.strip().str.lower())
            ref_vals  = set(df_ref[col].dropna().astype(str).str.strip().str.lower())
            overlap   = len(main_vals & ref_vals)
            if overlap > 0:
                good_keys.append((col, overlap / max(len(main_vals), 1)))
        good_keys.sort(key=lambda x: x[1], reverse=True)
        return [k[0] for k in good_keys]

    def find_join_keys_llm(self, df_main: pd.DataFrame,
                            df_ref: pd.DataFrame) -> list:
        if not self.llm.available:
            return []
        prompt = f"""Find JOIN keys between these two datasets (even if column names differ).

Main: {json.dumps(create_profile(df_main), indent=2)}
Reference: {json.dumps(create_profile(df_ref), indent=2)}

Respond ONLY with valid JSON (no markdown):
{{"join_pairs": [{{"main_column": "...", "ref_column": "...", "confidence": 0.0}}]}}
If none found: {{"join_pairs": []}}"""
        result = self.llm.call(prompt, max_tokens=600)
        if not result:
            return []
        try:
            clean  = result.strip().lstrip("```json").lstrip("```").rstrip("```").strip()
            pairs  = json.loads(clean).get("join_pairs", [])
            return [p for p in pairs if p.get("confidence", 0) >= 0.6]
        except Exception:
            return []

    def enrich(self, df_main: pd.DataFrame, df_ref: pd.DataFrame,
               ref_name: str = "reference") -> tuple[pd.DataFrame, dict]:
        rapport = {
            "ref_file":       ref_name,
            "method":         None,
            "join_keys":      [],
            "columns_added":  [],
            "rows_enriched":  0
        }
        _safe_stderr(f"\n   🔗 Cross-reference with: {ref_name}")
        exact_keys = self.find_join_keys_exact(df_main, df_ref)
        if exact_keys:
            _safe_stderr(f"      ✅ Exact keys: {exact_keys}")
            rapport["method"]    = "exact"
            rapport["join_keys"] = exact_keys
            df_main = self._merge(df_main, df_ref, exact_keys, exact_keys, rapport)
        else:
            _safe_stderr(f"      🤖 No exact key — consulting LLM...")
            llm_pairs = self.find_join_keys_llm(df_main, df_ref)
            if llm_pairs:
                main_keys = [p["main_column"] for p in llm_pairs]
                ref_keys  = [p["ref_column"]  for p in llm_pairs]
                _safe_stderr(f"      ✅ LLM keys: {list(zip(main_keys, ref_keys))}")
                rapport["method"] = "llm"
                df_ref_r = df_ref.rename(
                    columns={ref_keys[i]: main_keys[i] for i in range(len(main_keys))}
                )
                df_main = self._merge(df_main, df_ref_r, main_keys, main_keys, rapport)
            else:
                _safe_stderr(f"      ❌ No key found — reference skipped")
                rapport["method"] = "none"
        return df_main, rapport

    def _merge(self, df_main: pd.DataFrame, df_ref: pd.DataFrame,
               main_keys: list, ref_keys: list, rapport: dict) -> pd.DataFrame:
        cols_to_add  = [c for c in df_ref.columns
                        if c not in df_main.columns and c not in ref_keys]
        cols_to_fill = [c for c in df_ref.columns
                        if c in df_main.columns and c not in ref_keys]
        if not cols_to_add and not cols_to_fill:
            _safe_stderr(f"      ℹ️  No columns to enrich")
            return df_main

        df_m = df_main.copy()
        df_r = df_ref.copy()

        for key in main_keys:
            df_m[f"__k_{key}"] = df_m[key].astype(str).str.strip().str.lower()
        for key in ref_keys:
            df_r[f"__k_{key}"] = df_r[key].astype(str).str.strip().str.lower()

        merge_keys = [f"__k_{k}" for k in main_keys]
        ref_sub    = (
            df_r[merge_keys + cols_to_add + cols_to_fill]
            .copy()
            .add_suffix("_REF")
        )
        ref_sub = ref_sub.rename(
            columns={f"__k_{k}_REF": f"__k_{k}" for k in main_keys}
        )
        df_merged = df_m.merge(ref_sub, on=merge_keys, how="left")

        for col in cols_to_fill:
            ref_col = f"{col}_REF"
            if ref_col in df_merged.columns:
                nb     = df_merged[col].isna().sum()
                df_merged[col] = df_merged[col].combine_first(df_merged[ref_col])
                filled = nb - df_merged[col].isna().sum()
                if filled > 0:
                    _safe_stderr(f"      📥 {col}: {filled} NULLs filled")
                    rapport["rows_enriched"] += filled
                df_merged.drop(columns=[ref_col], inplace=True)
                self.ref_columns.add(col)

        for col in cols_to_add:
            ref_col = f"{col}_REF"
            if ref_col in df_merged.columns:
                df_merged[col] = df_merged[ref_col]
                df_merged.drop(columns=[ref_col], inplace=True)
                _safe_stderr(f"      ➕ Column added: {col}")
                rapport["columns_added"].append(col)
                self.ref_columns.add(col)

        df_merged.drop(
            columns=[c for c in df_merged.columns if c.startswith("__k_")],
            inplace=True
        )
        return df_merged


# ============================================================================
# 2. DERIVED COLUMNS RECALCULATOR
# ============================================================================

class DerivedColumnsRecalculator:
    def __init__(self, llm: GeminiLLM):
        self.llm = llm

    def detect_formulas(self, df: pd.DataFrame) -> list:
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
                    "min":     round(float(non_null.min()), 4),
                    "max":     round(float(non_null.max()), 4),
                    "mean":    round(float(non_null.mean()), 4),
                    "samples": [round(float(v), 4) for v in non_null.head(5).tolist()]
                }

        prompt = f"""Detect mathematical relationships between numeric columns (derived columns).

Numeric profile:
{json.dumps(profile, indent=2)}

Look for: multiplication, subtraction, addition, division between columns.
Verify using sample values.

Respond ONLY with valid JSON (no markdown):
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

Only include entries with confidence >= 0.85.
If none found: {{"derived_columns": []}}
Formula must be valid pandas — only arithmetic operations on df columns."""

        result = self.llm.call(prompt, max_tokens=600)
        if not result:
            return []
        try:
            clean   = result.strip().lstrip("```json").lstrip("```").rstrip("```").strip()
            formulas = json.loads(clean).get("derived_columns", [])
            return [f for f in formulas if f.get("confidence", 0) >= 0.85]
        except Exception:
            return []

    def recalculate(self, df: pd.DataFrame) -> tuple[pd.DataFrame, list]:
        _safe_stderr(f"\n🔢 Detecting derived columns...")
        formulas = self.detect_formulas(df)
        if not formulas:
            _safe_stderr("   ℹ️  No derived columns detected")
            return df, []

        _safe_stderr(f"   🤖 {len(formulas)} formula(s) detected")
        rapport = []

        for fi in formulas:
            target   = fi.get("target")
            formula  = fi.get("formula")
            readable = fi.get("formula_readable", formula)
            if not target or not formula or target not in df.columns:
                continue

            _safe_stderr(f"\n   📐 {target} = {readable}")
            try:
                # Only allow simple arithmetic on df columns (no function calls)
                if re.search(r'\b(?:import|exec|eval|open|os|sys|__)\b', formula):
                    _safe_stderr(f"      🚫 Unsafe formula rejected: {formula!r}")
                    continue

                expected = eval(formula, {"df": df, "pd": pd, "np": np})  # noqa: S
                if not isinstance(expected, pd.Series):
                    continue

                valid = df[target].notna() & expected.notna()
                if valid.sum() == 0:
                    continue

                match_pct = (
                    (abs(df.loc[valid, target] - expected[valid]) < 0.05).sum()
                    / valid.sum()
                )
                if match_pct < 0.70:
                    _safe_stderr(f"      ⚠️  {match_pct*100:.0f}% match — skipped")
                    continue

                _safe_stderr(f"      ✅ Formula validated ({match_pct*100:.0f}% match)")
                incoherent = valid & (abs(df[target] - expected) > 0.05)
                to_fix     = incoherent | df[target].isna()
                if to_fix.sum() == 0:
                    continue

                df.loc[to_fix, target] = expected[to_fix]
                count = int(to_fix.sum())
                _safe_stderr(f"      🔢 {count} values recalculated")
                rapport.append({"target": target, "formula": readable, "recalculated": count})

            except Exception as e:
                _safe_stderr(f"      ❌ Error: {e}")

        return df, rapport


# ============================================================================
# 3. LLM ENRICHER
# ============================================================================

class LLMEnricher:
    IDENTITY_KEYWORDS = ['id', 'email', 'phone', 'address', 'user', 'customer']

    def __init__(self, llm: GeminiLLM):
        self.llm = llm

    def enrich(self, df: pd.DataFrame, ref_columns: set = None,
               max_rows: int = 50) -> tuple[pd.DataFrame, list]:
        if not self.llm.available:
            _safe_stderr("   ℹ️  LLM Enricher disabled (no LLM)")
            return df, []

        ref_columns = set(ref_columns or [])
        null_cols   = [
            (c, int(df[c].isna().sum()))
            for c in df.columns if df[c].isna().sum() > 0
        ]
        if not null_cols:
            _safe_stderr("   ✅ No NULLs remaining")
            return df, []

        _safe_stderr(f"\n🤖 LLM Enricher — {len(null_cols)} columns with NULL")
        report = []

        for col, null_count in null_cols:
            if col in ref_columns:
                _safe_stderr(f"   🔗 {col}: skipped (reference column)")
                continue
            if any(kw in col.lower() for kw in self.IDENTITY_KEYWORDS):
                _safe_stderr(f"   🔒 {col}: skipped (identity column)")
                continue

            null_mask      = df[col].isna()
            rows_to_enrich = df[null_mask].head(max_rows)
            if len(rows_to_enrich) == 0:
                continue

            _safe_stderr(f"   🔍 {col} ({min(null_count, max_rows)} rows)")

            context_rows = []
            for idx, row in rows_to_enrich.iterrows():
                ctx = {
                    c: str(v) for c, v in row.items()
                    if c != col and pd.notna(v) and not c.startswith("FLAG_")
                }
                context_rows.append({"index": int(idx), "context": ctx})

            known = df[~null_mask][col].dropna().head(10).tolist()

            prompt = f"""Predict missing values for column "{col}".
Known values: {known}
Rows to predict:
{json.dumps(context_rows, indent=2)}

Respond ONLY with valid JSON (no markdown):
{{"predictions": [{{"index": 0, "predicted_value": "...", "confidence": 0.0}}]}}
Only include predictions with confidence >= 0.7."""

            result = self.llm.call(prompt, max_tokens=1000)
            if not result:
                continue
            try:
                clean       = result.strip().lstrip("```json").lstrip("```").rstrip("```").strip()
                predictions = json.loads(clean).get("predictions", [])
                applied     = 0
                for p in predictions:
                    if p.get("confidence", 0) >= 0.7 and p.get("index") is not None:
                        df.at[p["index"], col] = p["predicted_value"]
                        applied += 1
                _safe_stderr(f"      ✅ {applied}/{len(rows_to_enrich)} predicted")
                report.append({"column": col, "total_null": null_count, "enriched": applied})
            except Exception as e:
                _safe_stderr(f"      ❌ {e}")

        return df, report


# ============================================================================
# 4. IMPUTER
# ============================================================================

class Imputer:
    IDENTITY_KEYWORDS = [
        'id', 'email', 'phone', 'address', 'name',
        'user', 'customer', 'code'
    ]

    def impute(self, df: pd.DataFrame) -> pd.DataFrame:
        _safe_stderr(f"\n🔧 Final imputation...")
        null_cols = [
            (c, int(df[c].isna().sum()))
            for c in df.columns if df[c].isna().sum() > 0
        ]
        if not null_cols:
            _safe_stderr("   ✅ No NULLs")
            return df

        for col, count in null_cols:
            if any(kw in col.lower() for kw in self.IDENTITY_KEYWORDS):
                _safe_stderr(f"   🔒 {col}: {count} NULLs kept (identity)")
                continue
            if pd.api.types.is_numeric_dtype(df[col]):
                med = df[col].median()
                if pd.notna(med):
                    df[col] = df[col].fillna(med)
                    _safe_stderr(f"   🔢 {col}: median ({med:.2f})")
            else:
                mode = df[col].mode()
                if not mode.empty:
                    df[col] = df[col].fillna(mode[0])
                    _safe_stderr(f"   📝 {col}: mode ('{mode[0]}')")
        return df


# ============================================================================
# EXPORT
# ============================================================================

def export_results(df: pd.DataFrame, input_path: str,
                   output_dir: str, rapport: dict) -> tuple[str, str]:
    os.makedirs(output_dir, exist_ok=True)
    base     = os.path.splitext(os.path.basename(input_path))[0]
    out_csv  = os.path.join(output_dir, f"{base}_ENRICHED.csv")
    out_json = os.path.join(output_dir, f"{base}_REPORT.json")

    df.to_csv(out_csv, index=False, quoting=csv.QUOTE_MINIMAL)
    with open(out_json, 'w') as f:
        json.dump(rapport, f, indent=2, default=str)

    _safe_stderr(f"\n   💾 CSV: {out_csv}")
    _safe_stderr(f"   📊 Report: {out_json}")
    return out_csv, out_json


# ============================================================================
# MAIN PIPELINE
# ============================================================================

class CrossReferencePipeline:

    def __init__(self, llm: GeminiLLM, rules_path: str = None,
                 use_llm_enricher: bool = True):
        self.llm              = llm
        self.cross_ref_engine = CrossReferenceEngine(llm)
        self.validator        = ContextAwareValidator(llm, rules_path)
        self.derived_recalc   = DerivedColumnsRecalculator(llm)
        self.llm_enricher     = LLMEnricher(llm) if use_llm_enricher else None
        self.imputer          = Imputer()

    def run(self, main_file: str, ref_files: list,
            output_dir: str) -> dict | None:
        _safe_stderr(f"\n{'='*60}")
        _safe_stderr(f"🚀 CROSS REFERENCE PIPELINE v1.2")
        _safe_stderr(f"📂 {os.path.basename(main_file)}")
        if ref_files:
            _safe_stderr(f"📚 Refs: {[os.path.basename(r) for r in ref_files]}")
        _safe_stderr(f"{'='*60}")

        # Load
        df = load_csv(main_file, llm=self.llm)
        if df is None:
            return None

        rapport = {
            "main_file":      main_file,
            "ref_files":      ref_files,
            "initial_rows":   len(df),
            "initial_cols":   len(df.columns),
            "cross_reference": [],
            "validation":     [],
            "derived_columns": [],
            "enrichment":     [],
            "dedup_after_merge": 0,   # ← now properly tracked
            "final_rows":     0,
            "final_cols":     0,
            "null_remaining": 0
        }

        # ── Step 1: Cross Reference ──────────────────────────────────────────
        if ref_files:
            _safe_stderr(f"\n{'─'*40}\n1  CROSS REFERENCE\n{'─'*40}")
            for ref_file in ref_files:
                df_ref = load_csv(ref_file, llm=self.llm)
                if df_ref is None:
                    continue
                df, ref_r = self.cross_ref_engine.enrich(
                    df, df_ref, os.path.basename(ref_file)
                )
                rapport["cross_reference"].append(ref_r)

            # Deduplicate on the fully merged dataset
            rows_before_dedup = len(df)
            df = df.drop_duplicates()
            dedup_removed = rows_before_dedup - len(df)
            rapport["dedup_after_merge"] = dedup_removed   # ← fixed: was never set
            if dedup_removed > 0:
                _safe_stderr(
                    f"\n   🗑️  {dedup_removed} duplicates removed after merge"
                )
        else:
            _safe_stderr(f"\n   Simple mode (no reference files)")

        # ── Step 2: Validation ───────────────────────────────────────────────
        _safe_stderr(f"\n{'─'*40}\n2  VALIDATION\n{'─'*40}")
        df, val_r = self.validator.validate(df, filename=os.path.basename(main_file))
        rapport["validation"] = val_r

        # ── Step 3: Derived columns ──────────────────────────────────────────
        _safe_stderr(f"\n{'─'*40}\n3  DERIVED COLUMNS RECALCULATION\n{'─'*40}")
        df, der_r = self.derived_recalc.recalculate(df)
        rapport["derived_columns"] = der_r

        # ── Step 4: LLM Enricher ─────────────────────────────────────────────
        if self.llm_enricher and self.llm.available:
            _safe_stderr(f"\n{'─'*40}\n4  LLM ENRICHER\n{'─'*40}")
            df, enr_r = self.llm_enricher.enrich(
                df, ref_columns=self.cross_ref_engine.ref_columns
            )
            rapport["enrichment"] = enr_r

        # ── Step 5: Final imputation ─────────────────────────────────────────
        _safe_stderr(f"\n{'─'*40}\n5  FINAL IMPUTATION\n{'─'*40}")
        df = self.imputer.impute(df)

        # ── Step 6: Export ───────────────────────────────────────────────────
        _safe_stderr(f"\n{'─'*40}\n6  EXPORT\n{'─'*40}")
        rapport.update({
            "final_rows":     len(df),
            "final_cols":     len(df.columns),
            "null_remaining": int(df.isna().sum().sum())
        })
        out_csv, out_json = export_results(df, main_file, output_dir, rapport)

        _safe_stderr(f"\n{'='*60}")
        _safe_stderr(f"PIPELINE COMPLETE")
        _safe_stderr(f"   Rows   : {rapport['initial_rows']} → {rapport['final_rows']}")
        if rapport["dedup_after_merge"] > 0:
            _safe_stderr(
                f"   Deduped: {rapport['dedup_after_merge']} removed after merge"
            )
        _safe_stderr(f"   Columns: {rapport['initial_cols']} → {rapport['final_cols']}")
        _safe_stderr(f"   NULLs remaining: {rapport['null_remaining']}")
        _safe_stderr(f"{'='*60}")

        return {
            "status":        "success",
            "output_csv":    out_csv,
            "output_report": out_json,
            "rapport":       rapport
        }


# ============================================================================
# CLI
# ============================================================================

def main():
    parser = argparse.ArgumentParser(description="Cross Reference Engine v1.2")
    parser.add_argument("files", nargs="+")
    parser.add_argument("--output", default="cross_ref_output")
    parser.add_argument("--rules",  default=None)
    parser.add_argument("--no-llm-enricher", action="store_true")
    args = parser.parse_args()

    for f in args.files:
        if not os.path.exists(f):
            print(json.dumps({"status": "error", "message": f"Not found: {f}"}))
            sys.exit(1)

    llm      = GeminiLLM()
    pipeline = CrossReferencePipeline(
        llm,
        rules_path=args.rules,
        use_llm_enricher=not args.no_llm_enricher
    )
    result = pipeline.run(args.files[0], args.files[1:], args.output)

    if result:
        r = result["rapport"]
        print(json.dumps({
            "status":            "success",
            "output_csv":        result["output_csv"],
            "output_report":     result["output_report"],
            "final_rows":        r["final_rows"],
            "final_cols":        r["final_cols"],
            "null_remaining":    r["null_remaining"],
            "dedup_after_merge": r["dedup_after_merge"],
            "rapport":           r
        }))
    else:
        print(json.dumps({"status": "error", "message": "Pipeline failed"}))
        sys.exit(1)


if __name__ == "__main__":
    main()