#!/usr/bin/env python3
"""
Master Workflow Script - v2.1
==============================
Orchestre le nettoyage de données et le cross-référencement.

v2.1 changes vs v2.0:
  - Uses sys.executable instead of hardcoded 'python3' — works on Windows,
    virtualenvs, conda, and any custom Python installation
  - Script paths are resolved relative to THIS file's directory, not the
    current working directory — works regardless of where workflow.py is
    called from
  - check_required_scripts() uses SCRIPT_DIR-relative paths consistently

Modes disponibles:
  clean      → Nettoyage seul (data_cleaner.py)
  cross-ref  → Cross-référence seul (cross_reference.py)
  full       → Pipeline complet (clean → cross-ref)
  test       → Génère données de test + pipeline complet

Usage:
  python workflow.py clean file1.csv file2.csv --output cleaned/
  python workflow.py cross-ref main.csv ref1.csv ref2.csv --output results/
  python workflow.py full main.csv ref1.csv --clean-dir cleaned/ --analysis-dir results/
  python workflow.py test
"""

import os
import sys
import argparse
import subprocess
import json
from datetime import datetime

# ── Resolve all sibling scripts relative to this file, not CWD ───────────────
# This means workflow.py works correctly when called from any directory:
#   /some/other/dir$ python /path/to/scripts/workflow.py clean file.csv
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))

# The Python interpreter running THIS script — guaranteed to be the same
# environment (virtualenv, conda, system) with all dependencies installed.
PYTHON = sys.executable


# ============================================================================
# HELPERS
# ============================================================================

def script_path(name: str) -> str:
    """Return absolute path to a sibling script."""
    return os.path.join(SCRIPT_DIR, name)


def print_header(title: str):
    print(f"\n{'='*60}")
    print(f"  {title}")
    print(f"{'='*60}")


def print_step(title: str):
    print(f"\n{'─'*40}")
    print(f"  {title}")
    print(f"{'─'*40}")


def run_command(cmd: list, description: str) -> tuple[bool, dict | None]:
    """
    Execute a command and return (success, stdout_json).
    cmd[0] is always PYTHON — set by the callers, never hardcoded here.
    """
    print_step(f"🔄 {description}")
    print(f"  Command: {' '.join(cmd)}\n")

    result = subprocess.run(cmd, capture_output=True, text=True)

    # Print stderr (script progress logs)
    if result.stderr:
        for line in result.stderr.strip().split('\n'):
            print(f"  {line}")

    # Parse JSON from stdout
    stdout_data = None
    if result.stdout.strip():
        try:
            stdout_data = json.loads(result.stdout.strip())
        except Exception:
            print(result.stdout)

    if result.returncode != 0:
        print(f"\n  ❌ Failed: {description}")
        if stdout_data:
            print(f"  Error: {stdout_data.get('message', 'Unknown')}")
        return False, stdout_data

    print(f"\n  ✅ Success: {description}")
    return True, stdout_data


def check_required_scripts(mode: str) -> bool:
    """Verify that all required sibling scripts are present."""
    required = {
        'clean':     ['data_cleaner.py'],
        'cross-ref': ['cross_reference.py'],
        'full':      ['data_cleaner.py', 'cross_reference.py'],
        'test':      ['data_cleaner.py', 'cross_reference.py', 'generate_test_data.py']
    }

    missing = [s for s in required[mode] if not os.path.exists(script_path(s))]
    if missing:
        print(f"\n❌ Missing scripts: {missing}")
        print(f"   Expected in: {SCRIPT_DIR}")
        return False
    return True


def check_input_files(files: list) -> bool:
    """Verify that all input files exist."""
    missing = [f for f in files if not os.path.exists(f)]
    if missing:
        print(f"\n❌ Files not found: {missing}")
        return False
    return True


# ============================================================================
# WORKFLOWS
# ============================================================================

def workflow_clean_only(input_files: list, output_dir: str) -> list:
    """
    Workflow 1: Clean only.
    Produces one _CLEANED.csv per input file.
    """
    print_header("📋 WORKFLOW: DATA CLEANING")
    os.makedirs(output_dir, exist_ok=True)

    cleaned_files = []
    failed_files  = []

    for input_file in input_files:
        basename    = os.path.splitext(os.path.basename(input_file))[0]
        output_file = os.path.join(output_dir, f"{basename}_CLEANED.csv")

        cmd = [PYTHON, script_path('data_cleaner.py'), input_file, output_file]
        success, data = run_command(cmd, f"Cleaning: {os.path.basename(input_file)}")

        if success and os.path.exists(output_file):
            cleaned_files.append(output_file)
            if data:
                rows = data.get('rows', '?')
                cols = data.get('columns', '?')
                null = data.get('null_remaining', '?')
                print(f"  📊 {rows} rows × {cols} columns | {null} NULLs remaining")
        else:
            failed_files.append(input_file)

    print_header("📋 CLEANING SUMMARY")
    print(f"  ✅ Cleaned : {len(cleaned_files)}/{len(input_files)} files")
    print(f"  📂 Output  : {output_dir}/")
    if failed_files:
        print(f"  ❌ Failed  : {[os.path.basename(f) for f in failed_files]}")

    return cleaned_files


def workflow_cross_reference_only(
    input_files: list,
    output_dir: str,
    rules_path: str = None,
    no_llm_enricher: bool = False
) -> bool:
    """
    Workflow 2: Cross-reference only.
    First file = main, remaining files = references.
    Produces _ENRICHED.csv + _REPORT.json.
    """
    print_header("🔗 WORKFLOW: CROSS-REFERENCING")

    if not input_files:
        print("  ❌ No input files provided")
        return False

    main_file = input_files[0]
    ref_files = input_files[1:]

    print(f"  📂 Main      : {os.path.basename(main_file)}")
    if ref_files:
        print(f"  📚 References: {[os.path.basename(r) for r in ref_files]}")
    else:
        print(f"  ℹ️  Simple mode (no reference files)")

    cmd = [PYTHON, script_path('cross_reference.py')] + input_files + ['--output', output_dir]
    if rules_path:
        cmd += ['--rules', rules_path]
    if no_llm_enricher:
        cmd += ['--no-llm-enricher']

    success, data = run_command(cmd, f"Cross-reference: {os.path.basename(main_file)}")

    if success and data:
        print(f"\n  📊 Results:")
        print(f"     Final rows     : {data.get('final_rows', '?')}")
        print(f"     Columns        : {data.get('final_cols', '?')}")
        print(f"     NULLs remaining: {data.get('null_remaining', '?')}")
        print(f"     Deduped        : {data.get('dedup_after_merge', 0)} rows removed after merge")
        print(f"     CSV            : {data.get('output_csv', '?')}")
        print(f"     Report         : {data.get('output_report', '?')}")

    return success


def workflow_full_pipeline(
    input_files: list,
    clean_dir: str,
    analysis_dir: str,
    rules_path: str = None,
    no_llm_enricher: bool = False
) -> bool:
    """
    Workflow 3: Full pipeline.
    Step 1 → Clean all files.
    Step 2 → Cross-reference (first cleaned file = main).
    """
    print_header("🚀 WORKFLOW: FULL PIPELINE")

    # Step 1: Clean
    print(f"\n📍 STEP 1/2 — Cleaning")
    cleaned_files = workflow_clean_only(input_files, clean_dir)

    if not cleaned_files:
        print("\n❌ No files cleaned — aborting pipeline")
        return False

    # Step 2: Cross-reference
    print(f"\n📍 STEP 2/2 — Cross-referencing")
    success = workflow_cross_reference_only(
        cleaned_files,
        analysis_dir,
        rules_path=rules_path,
        no_llm_enricher=no_llm_enricher
    )

    if success:
        print_header("✅ FULL PIPELINE COMPLETE")
        print(f"  📂 Cleaned  : {clean_dir}/")
        print(f"  📊 Enriched : {analysis_dir}/")

    return success


def workflow_test_mode(no_llm_enricher: bool = False) -> bool:
    """
    Workflow 4: Test mode.
    1. Generate test data via generate_test_data.py.
    2. Run the full pipeline on that data.
    """
    print_header("🧪 WORKFLOW: TEST MODE")

    print(f"\n📍 Generating test data...")
    cmd     = [PYTHON, script_path('generate_test_data.py')]
    success, _ = run_command(cmd, "Generating test data")

    if not success:
        print("❌ Test data generation failed")
        return False

    test_dir = os.path.join(SCRIPT_DIR, "test_data")
    if not os.path.exists(test_dir):
        print(f"❌ Folder '{test_dir}' not found")
        return False

    test_files = sorted([
        os.path.join(test_dir, f)
        for f in os.listdir(test_dir)
        if f.endswith('.csv')
    ])

    if not test_files:
        print("❌ No test CSV files found")
        return False

    print(f"\n  📊 {len(test_files)} test file(s):")
    for f in test_files:
        print(f"     • {os.path.basename(f)}")

    timestamp    = datetime.now().strftime("%Y%m%d_%H%M%S")
    clean_dir    = os.path.join(SCRIPT_DIR, f"test_cleaned_{timestamp}")
    analysis_dir = os.path.join(SCRIPT_DIR, f"test_analysis_{timestamp}")

    return workflow_full_pipeline(
        test_files,
        clean_dir,
        analysis_dir,
        no_llm_enricher=no_llm_enricher
    )


# ============================================================================
# CLI
# ============================================================================

def main():
    parser = argparse.ArgumentParser(
        description="Master Workflow — Cleaning and Cross Referencing v2.1",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Examples:

  # Clean only:
  python workflow.py clean orders.csv products.csv --output cleaned/

  # Cross-reference only (1st file = main):
  python workflow.py cross-ref orders_CLEANED.csv products.csv --output results/

  # Full pipeline:
  python workflow.py full orders.csv products.csv --clean-dir cleaned/ --analysis-dir results/

  # With custom validation rules:
  python workflow.py full orders.csv products.csv --rules rules.json

  # Test mode:
  python workflow.py test
        """
    )

    parser.add_argument(
        'mode',
        choices=['clean', 'cross-ref', 'full', 'test'],
        help='Workflow mode'
    )
    parser.add_argument(
        'files', nargs='*',
        help='Input files (for clean / cross-ref / full modes)'
    )
    parser.add_argument(
        '--output', default='output',
        help='Output directory (clean and cross-ref modes)'
    )
    parser.add_argument(
        '--clean-dir', default='cleaned_data',
        help='Directory for cleaned files (full mode)'
    )
    parser.add_argument(
        '--analysis-dir', default='analysis_results',
        help='Directory for enriched results (full mode)'
    )
    parser.add_argument(
        '--rules', default=None,
        help='Path to rules.json (custom validation rules)'
    )
    parser.add_argument(
        '--no-llm-enricher', action='store_true',
        help='Disable LLM Enricher (faster, fewer tokens)'
    )

    args = parser.parse_args()

    # ── Validation ───────────────────────────────────────────────────────────
    if not check_required_scripts(args.mode):
        return 1

    if args.mode != 'test':
        if not args.files:
            print(f"\n❌ No input files provided for mode '{args.mode}'")
            parser.print_help()
            return 1
        if not check_input_files(args.files):
            return 1

    if args.rules and not os.path.exists(args.rules):
        print(f"\n❌ rules.json not found: {args.rules}")
        return 1

    # ── Header ───────────────────────────────────────────────────────────────
    print_header("🔧 DATA PROCESSING SUITE v2.1")
    print(f"  Mode      : {args.mode.upper()}")
    print(f"  Date      : {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print(f"  Python    : {PYTHON}")
    print(f"  Script dir: {SCRIPT_DIR}")
    if args.files:
        print(f"  Files     : {[os.path.basename(f) for f in args.files]}")
    if args.rules:
        print(f"  Rules     : {args.rules}")

    # ── Execute ──────────────────────────────────────────────────────────────
    success = False

    if args.mode == 'clean':
        cleaned = workflow_clean_only(args.files, args.output)
        success = len(cleaned) > 0

    elif args.mode == 'cross-ref':
        success = workflow_cross_reference_only(
            args.files, args.output,
            rules_path=args.rules,
            no_llm_enricher=args.no_llm_enricher
        )

    elif args.mode == 'full':
        success = workflow_full_pipeline(
            args.files,
            args.clean_dir,
            args.analysis_dir,
            rules_path=args.rules,
            no_llm_enricher=args.no_llm_enricher
        )

    elif args.mode == 'test':
        success = workflow_test_mode(no_llm_enricher=args.no_llm_enricher)

    # ── Final status ─────────────────────────────────────────────────────────
    print_header("✅ TERMINATED" if success else "❌ FAILED")
    return 0 if success else 1


if __name__ == "__main__":
    sys.exit(main())