#!/usr/bin/env python3
"""
Master Workflow Script v2.0
============================
Orchestre le nettoyage de données et le cross-référencement

Modes:
  clean      -> Nettoyage seul
  cross-ref  -> Cross-référence seul
  full       -> Pipeline complet (clean -> cross-ref)
  test       -> Données de test + pipeline complet

Usage:
  python workflow.py clean file1.csv --output cleaned/
  python workflow.py cross-ref main.csv ref.csv --output results/
  python workflow.py full main.csv ref.csv --clean-dir cleaned/ --analysis-dir results/
  python workflow.py test
"""

import os
import sys
import csv
import argparse
import subprocess
import json
from datetime import datetime

# ============================================================================
# HELPERS
# ============================================================================

def print_header(title):
    print(f"\n{'='*60}")
    print(f"  {title}")
    print(f"{'='*60}")

def print_step(title):
    print(f"\n{'─'*40}")
    print(f"  {title}")
    print(f"{'─'*40}")

def run_command(cmd, description):
    """Execute a command, stream stderr logs, return (success, json_data)."""
    print_step(f"Running: {description}")
    print(f"  Cmd: {' '.join(cmd)}\n")

    # encoding='utf-8' pour les logs, errors='replace' évite UnicodeDecodeError
    # sur les terminaux Windows (cp1252) qui ne savent pas décoder les emojis
    result = subprocess.run(
        cmd, capture_output=True,
        encoding='utf-8', errors='replace'
    )

    if result.stderr:
        for line in result.stderr.strip().split('\n'):
            # Ré-encoder proprement pour le terminal Windows
            try:
                print(f"  {line}")
            except UnicodeEncodeError:
                print(f"  {line.encode('ascii', errors='replace').decode('ascii')}")

    stdout_data = None
    if result.stdout.strip():
        try:
            stdout_data = json.loads(result.stdout.strip())
        except Exception:
            print(result.stdout)

    if result.returncode != 0:
        print(f"\n  FAIL: {description}")
        if stdout_data:
            print(f"  Error: {stdout_data.get('message', 'Unknown')}")
        return False, stdout_data

    print(f"\n  OK: {description}")
    return True, stdout_data

def check_required_scripts(mode):
    required = {
        'clean':     ['data_cleaner.py'],
        'cross-ref': ['cross_reference.py'],
        'full':      ['data_cleaner.py', 'cross_reference.py'],
        'test':      ['data_cleaner.py', 'cross_reference.py'],
    }
    missing = [s for s in required[mode] if not os.path.exists(s)]
    if missing:
        print(f"\nMissing scripts: {missing}")
        return False
    return True

def check_input_files(files):
    missing = [f for f in files if not os.path.exists(f)]
    if missing:
        print(f"\nFiles not found: {missing}")
        return False
    return True

# ============================================================================
# WORKFLOWS
# ============================================================================

def workflow_clean_only(input_files, output_dir):
    print_header("WORKFLOW: DATA CLEANING")
    os.makedirs(output_dir, exist_ok=True)
    cleaned_files, failed_files = [], []

    for input_file in input_files:
        basename    = os.path.splitext(os.path.basename(input_file))[0]
        output_file = os.path.join(output_dir, f"{basename}_CLEANED.csv")
        cmd = ["python", "data_cleaner.py", input_file, output_file]
        success, data = run_command(cmd, f"Clean: {os.path.basename(input_file)}")
        if success and os.path.exists(output_file):
            cleaned_files.append(output_file)
            if data:
                print(f"  {data.get('rows','?')} rows x {data.get('columns','?')} cols | {data.get('null_remaining','?')} NULLs")
        else:
            failed_files.append(input_file)

    print_header("CLEANING SUMMARY")
    print(f"  Cleaned : {len(cleaned_files)}/{len(input_files)}")
    print(f"  Output  : {output_dir}/")
    if failed_files:
        print(f"  Failed  : {[os.path.basename(f) for f in failed_files]}")
    return cleaned_files


def workflow_cross_reference_only(input_files, output_dir, rules_path=None, no_llm_enricher=False):
    print_header("WORKFLOW: CROSS-REFERENCE")
    if not input_files:
        print("  No input files"); return False

    main_file = input_files[0]
    ref_files = input_files[1:]
    print(f"  Main : {os.path.basename(main_file)}")
    if ref_files:
        print(f"  Refs : {[os.path.basename(r) for r in ref_files]}")

    cmd = ["python", "cross_reference.py"] + input_files + ["--output", output_dir]
    if rules_path:
        cmd += ["--rules", rules_path]
    if no_llm_enricher:
        cmd += ["--no-llm-enricher"]

    success, data = run_command(cmd, f"Cross-ref: {os.path.basename(main_file)}")
    if success and data:
        print(f"\n  Results:")
        print(f"    Rows     : {data.get('final_rows','?')}")
        print(f"    Columns  : {data.get('final_cols','?')}")
        print(f"    NULLs    : {data.get('null_remaining','?')}")
        print(f"    CSV      : {data.get('output_csv','?')}")
        print(f"    Report   : {data.get('output_report','?')}")
    return success


def workflow_full_pipeline(input_files, clean_dir, analysis_dir, rules_path=None, no_llm_enricher=False):
    print_header("WORKFLOW: FULL PIPELINE")

    print(f"\nSTEP 1/2 — Cleaning")
    cleaned_files = workflow_clean_only(input_files, clean_dir)
    if not cleaned_files:
        print("\nNo cleaned files — stopping."); return False

    print(f"\nSTEP 2/2 — Cross-reference")
    success = workflow_cross_reference_only(
        cleaned_files, analysis_dir, rules_path, no_llm_enricher
    )
    if success:
        print_header("FULL PIPELINE DONE")
        print(f"  Cleaned  : {clean_dir}/")
        print(f"  Enriched : {analysis_dir}/")
    return success


def workflow_test_mode(no_llm_enricher=False):
    print_header("WORKFLOW: TEST MODE")

    os.makedirs("test_data", exist_ok=True)

    # Données avec formats de dates variés — symboles monétaires ASCII uniquement
    main_rows = [
        ["id", "customer",      "order_date",     "delivery_date", "price",       "quantity"],
        [1,    "Alice Martin",  "01/mar/2023",     "15/01/2024",   "USD 25.50",   3],
        [2,    "Bob Dupont",    "17-mai-2022",     "20-feb-2024",  "EUR 1200",    1],
        [3,    "Carol Smith",   "08-aout-2021",    "2024-07-22",   "Free",        5],
        [4,    "David Lee",     "2024-01-15",      "01/Apr/2024",  "GBP 99.99",   2],
        [5,    "Emma Brown",    "July 4 2023",     "5-may-2024",   "45.00",       4],
        [6,    "Frank Wilson",  "2023/11/30",      "30-Nov-2024",  "GBP 45.00",   1],
        [7,    "Grace Taylor",  "15-Jun-2022",     "04/jul/2024",  "USD 1200.00", 2],
        [8,    "Henry Clark",   "18.06.2021",      "08-08-2024",   "INR 5000",    3],
        [9,    "Iris Johnson",  "09/09/2020",      "09-sep-2024",  "JPY 12000",   1],
        [10,   "Jack Davis",    "10-10-2019",      "10/oct/2024",  "EUR 250.75",  6],
    ]

    ref_rows = [
        ["id", "customer",     "region",     "category"],
        [1,    "Alice Martin", "Europe",     "Premium"],
        [2,    "Bob Dupont",   "France",     "Standard"],
        [3,    "Carol Smith",  "UK",         "Premium"],
        [4,    "David Lee",    "USA",        "Standard"],
        [5,    "Emma Brown",   "Canada",     "Premium"],
        [6,    "Frank Wilson", "Germany",    "Basic"],
        [7,    "Grace Taylor", "Spain",      "Standard"],
        [8,    "Henry Clark",  "India",      "Basic"],
        [9,    "Iris Johnson", "Japan",      "Premium"],
        [10,   "Jack Davis",   "Australia",  "Standard"],
    ]

    main_path = "test_data/orders.csv"
    ref_path  = "test_data/customers.csv"

    # Toujours utf-8 pour eviter UnicodeEncodeError sur Windows (cp1252)
    with open(main_path, 'w', newline='', encoding='utf-8') as f:
        csv.writer(f).writerows(main_rows)
    with open(ref_path, 'w', newline='', encoding='utf-8') as f:
        csv.writer(f).writerows(ref_rows)

    print(f"  orders.csv   created ({len(main_rows)-1} rows)")
    print(f"  customers.csv created ({len(ref_rows)-1} rows)")
    print(f"\n  Date formats to parse:")
    for row in main_rows[1:]:
        print(f"    order={str(row[2]):<22}  delivery={row[3]}")

    timestamp    = datetime.now().strftime("%Y%m%d_%H%M%S")
    clean_dir    = f"test_cleaned_{timestamp}"
    analysis_dir = f"test_analysis_{timestamp}"

    return workflow_full_pipeline(
        [main_path, ref_path],
        clean_dir, analysis_dir,
        no_llm_enricher=no_llm_enricher
    )

# ============================================================================
# CLI
# ============================================================================

def main():
    parser = argparse.ArgumentParser(
        description="Master Workflow v2.0 — Data Cleaning & Cross-Reference",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Examples:
  python workflow.py clean orders.csv products.csv --output cleaned/
  python workflow.py cross-ref orders_CLEANED.csv products.csv --output results/
  python workflow.py full orders.csv products.csv --clean-dir cleaned/ --analysis-dir results/
  python workflow.py test
        """
    )
    parser.add_argument('mode', choices=['clean', 'cross-ref', 'full', 'test'])
    parser.add_argument('files', nargs='*', help='Input files')
    parser.add_argument('--output',       default='output')
    parser.add_argument('--clean-dir',    default='cleaned_data')
    parser.add_argument('--analysis-dir', default='analysis_results')
    parser.add_argument('--rules',        default=None)
    parser.add_argument('--no-llm-enricher', action='store_true')
    args = parser.parse_args()

    if not check_required_scripts(args.mode):
        return 1
    if args.mode != 'test':
        if not args.files:
            print(f"No input files for mode '{args.mode}'")
            parser.print_help(); return 1
        if not check_input_files(args.files):
            return 1

    print_header("DATA PROCESSING SUITE v2.0")
    print(f"  Mode : {args.mode.upper()}")
    print(f"  Date : {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    if args.files:
        print(f"  Files: {[os.path.basename(f) for f in args.files]}")

    success = False
    if args.mode == 'clean':
        cleaned = workflow_clean_only(args.files, args.output)
        success = len(cleaned) > 0
    elif args.mode == 'cross-ref':
        success = workflow_cross_reference_only(
            args.files, args.output, args.rules, args.no_llm_enricher)
    elif args.mode == 'full':
        success = workflow_full_pipeline(
            args.files, args.clean_dir, args.analysis_dir,
            args.rules, args.no_llm_enricher)
    elif args.mode == 'test':
        success = workflow_test_mode(no_llm_enricher=args.no_llm_enricher)

    print_header("DONE" if success else "FAILED")
    return 0 if success else 1


if __name__ == "__main__":
    sys.exit(main())