# #!/usr/bin/env python3
# """
# Master Workflow Script
# Orchestre le nettoyage de données et le cross-référencement
# """

# import os
# import sys
# import argparse
# import subprocess
# import json
# from datetime import datetime

# def run_command(cmd, description):
#     """Exécute une commande et gère les erreurs"""
#     print(f"\n{'='*60}")
#     print(f"🔄 {description}")
#     print(f"{'='*60}")
#     print(f"Commande: {' '.join(cmd)}\n")
    
#     result = subprocess.run(cmd, capture_output=True, text=True)
    
#     if result.stdout:
#         print(result.stdout)
#     if result.stderr:
#         print(result.stderr, file=sys.stderr)
    
#     if result.returncode != 0:
#         print(f"❌ Erreur lors de: {description}")
#         return False
    
#     print(f"✅ {description} - Terminé")
#     return True

# def workflow_clean_only(input_files, output_dir):
#     """Workflow 1: Nettoyage seulement"""
#     print("\n" + "="*60)
#     print("📋 WORKFLOW 1: NETTOYAGE DE DONNÉES")
#     print("="*60)
    
#     if not os.path.exists(output_dir):
#         os.makedirs(output_dir)
    
#     cleaned_files = []
#     for input_file in input_files:
#         basename = os.path.splitext(os.path.basename(input_file))[0]
#         output_file = os.path.join(output_dir, f"{basename}_CLEANED.csv")
        
#         cmd = ["python3", "data_cleaner.py", input_file, output_file]
        
#         if run_command(cmd, f"Nettoyage de {os.path.basename(input_file)}"):
#             cleaned_files.append(output_file)
    
#     print(f"\n✅ {len(cleaned_files)} fichiers nettoyés dans '{output_dir}'")
#     return cleaned_files

# def workflow_cross_reference_only(input_files, output_dir):
#     """Workflow 2: Cross-référence seulement"""
#     print("\n" + "="*60)
#     print("🔗 WORKFLOW 2: CROSS-RÉFÉRENCEMENT")
#     print("="*60)
    
#     cmd = ["python3", "cross_reference.py"] + input_files + ["--output", output_dir]
    
#     if run_command(cmd, "Analyse de cross-référencement"):
#         print(f"\n✅ Résultats disponibles dans '{output_dir}'")
#         return True
#     return False

# def workflow_full_pipeline(input_files, clean_dir, analysis_dir):
#     """Workflow 3: Pipeline complet (nettoyage + cross-référence)"""
#     print("\n" + "="*60)
#     print("🚀 WORKFLOW 3: PIPELINE COMPLET")
#     print("="*60)
    
#     # Étape 1: Nettoyage
#     print("\n📍 ÉTAPE 1/2: Nettoyage des données...")
#     cleaned_files = workflow_clean_only(input_files, clean_dir)
    
#     if not cleaned_files:
#         print("❌ Aucun fichier nettoyé, arrêt du pipeline")
#         return False
    
#     # Étape 2: Cross-référence
#     print("\n📍 ÉTAPE 2/2: Cross-référencement...")
#     success = workflow_cross_reference_only(cleaned_files, analysis_dir)
    
#     if success:
#         print("\n" + "="*60)
#         print("✅ PIPELINE COMPLET TERMINÉ!")
#         print("="*60)
#         print(f"📂 Données nettoyées: {clean_dir}")
#         print(f"📊 Analyse: {analysis_dir}")
#         return True
    
#     return False

# def workflow_test_mode():
#     """Workflow 4: Mode test avec données générées"""
#     print("\n" + "="*60)
#     print("🧪 WORKFLOW 4: MODE TEST")
#     print("="*60)
    
#     # Générer les données de test
#     if not os.path.exists("generate_test_data.py"):
#         print("❌ Fichier generate_test_data.py introuvable")
#         return False
    
#     print("\n📍 Génération des données de test...")
#     cmd = ["python3", "generate_test_data.py"]
#     if not run_command(cmd, "Génération de données de test"):
#         return False
    
#     # Lister les fichiers de test
#     test_files = [os.path.join("test_data", f) for f in os.listdir("test_data") if f.endswith('.csv')]
    
#     if not test_files:
#         print("❌ Aucun fichier de test généré")
#         return False
    
#     print(f"\n📊 {len(test_files)} fichiers de test générés")
    
#     # Exécuter le pipeline complet sur les données de test
#     timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
#     return workflow_full_pipeline(
#         test_files,
#         f"test_cleaned_{timestamp}",
#         f"test_analysis_{timestamp}"
#     )

# def main():
#     parser = argparse.ArgumentParser(
#         description="Master Workflow - Nettoyage et Cross-référencement de données",
#         formatter_class=argparse.RawDescriptionHelpFormatter,
#         epilog="""
# Exemples d'utilisation:

# 1. Nettoyage seulement:
#    python workflow.py clean file1.csv file2.csv --output cleaned_data

# 2. Cross-référence seulement:
#    python workflow.py cross-ref file1.csv file2.csv --output analysis

# 3. Pipeline complet:
#    python workflow.py full file1.csv file2.csv --clean-dir cleaned --analysis-dir results

# 4. Mode test (génère et traite des données de test):
#    python workflow.py test
#         """
#     )
    
#     parser.add_argument(
#         'mode',
#         choices=['clean', 'cross-ref', 'full', 'test'],
#         help='Mode de workflow à exécuter'
#     )
    
#     parser.add_argument(
#         'files',
#         nargs='*',
#         help='Fichiers d\'entrée (requis pour clean, cross-ref, full)'
#     )
    
#     parser.add_argument(
#         '--output',
#         default='output',
#         help='Dossier de sortie (pour clean et cross-ref)'
#     )
    
#     parser.add_argument(
#         '--clean-dir',
#         default='cleaned_data',
#         help='Dossier pour données nettoyées (mode full)'
#     )
    
#     parser.add_argument(
#         '--analysis-dir',
#         default='analysis_results',
#         help='Dossier pour résultats d\'analyse (mode full)'
#     )
    
#     args = parser.parse_args()
    
#     # Vérifier que les scripts requis existent
#     required_scripts = {
#         'clean': ['data_cleaner.py'],
#         'cross-ref': ['cross_reference.py'],
#         'full': ['data_cleaner.py', 'cross_reference.py'],
#         'test': ['data_cleaner.py', 'cross_reference.py', 'generate_test_data.py']
#     }
    
#     for script in required_scripts[args.mode]:
#         if not os.path.exists(script):
#             print(f"❌ Erreur: Fichier requis '{script}' introuvable")
#             print(f"   Assurez-vous que tous les scripts sont dans le même dossier")
#             return 1
    
#     # Vérifier les fichiers d'entrée (sauf pour mode test)
#     if args.mode != 'test':
#         if not args.files:
#             print(f"❌ Erreur: Aucun fichier d'entrée spécifié pour le mode '{args.mode}'")
#             parser.print_help()
#             return 1
        
#         for f in args.files:
#             if not os.path.exists(f):
#                 print(f"❌ Erreur: Fichier '{f}' introuvable")
#                 return 1
    
#     # Exécuter le workflow approprié
#     print("\n" + "="*60)
#     print("🔧 DATA PROCESSING SUITE - MASTER WORKFLOW")
#     print("="*60)
#     print(f"Mode: {args.mode.upper()}")
#     print(f"Date: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    
#     success = False
    
#     if args.mode == 'clean':
#         cleaned = workflow_clean_only(args.files, args.output)
#         success = len(cleaned) > 0
    
#     elif args.mode == 'cross-ref':
#         success = workflow_cross_reference_only(args.files, args.output)
    
#     elif args.mode == 'full':
#         success = workflow_full_pipeline(args.files, args.clean_dir, args.analysis_dir)
    
#     elif args.mode == 'test':
#         success = workflow_test_mode()
    
#     # Résumé final
#     print("\n" + "="*60)
#     if success:
#         print("✅ WORKFLOW TERMINÉ AVEC SUCCÈS")
#     else:
#         print("❌ WORKFLOW ÉCHOUÉ")
#     print("="*60)
    
#     return 0 if success else 1

# if __name__ == "__main__":
#     sys.exit(main())

#!/usr/bin/env python3
"""
Master Workflow Script v2.0
============================
Orchestre le nettoyage de données et le cross-référencement

Modes disponibles:
  clean      → Nettoyage seul (data_cleaner.py)
  cross-ref  → Cross-référence seul (cross_reference.py)
  full       → Pipeline complet (clean → cross-ref)
  test       → Génère données de test + pipeline complet

Usage:
  python3 workflow.py clean file1.csv file2.csv --output cleaned/
  python3 workflow.py cross-ref main.csv ref1.csv ref2.csv --output results/
  python3 workflow.py full main.csv ref1.csv --clean-dir cleaned/ --analysis-dir results/
  python3 workflow.py test
"""

import os
import sys
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
    """Exécute une commande et retourne (success, stdout_json)"""
    print_step(f"🔄 {description}")
    print(f"  Commande: {' '.join(cmd)}\n")

    result = subprocess.run(cmd, capture_output=True, text=True)

    # Afficher stderr (logs du script)
    if result.stderr:
        for line in result.stderr.strip().split('\n'):
            print(f"  {line}")

    # Parser le JSON de sortie stdout
    stdout_data = None
    if result.stdout.strip():
        try:
            stdout_data = json.loads(result.stdout.strip())
        except:
            print(result.stdout)

    if result.returncode != 0:
        print(f"\n  ❌ Échec: {description}")
        if stdout_data:
            print(f"  Erreur: {stdout_data.get('message', 'Inconnue')}")
        return False, stdout_data

    print(f"\n  ✅ Succès: {description}")
    return True, stdout_data

def check_required_scripts(mode):
    """Vérifie que les scripts requis sont présents"""
    required = {
        'clean':     ['data_cleaner.py'],
        'cross-ref': ['cross_reference.py'],
        'full':      ['data_cleaner.py', 'cross_reference.py'],
        'test':      ['data_cleaner.py', 'cross_reference.py', 'generate_test_data.py']
    }

    missing = [s for s in required[mode] if not os.path.exists(s)]
    if missing:
        print(f"\n❌ Scripts manquants: {missing}")
        print(f"   Assurez-vous que tous les scripts sont dans le même dossier.")
        return False
    return True

def check_input_files(files):
    """Vérifie que les fichiers d'entrée existent"""
    missing = [f for f in files if not os.path.exists(f)]
    if missing:
        print(f"\n❌ Fichiers introuvables: {missing}")
        return False
    return True

# ============================================================================
# WORKFLOWS
# ============================================================================

def workflow_clean_only(input_files, output_dir):
    """
    Workflow 1: Nettoyage seul
    → Un CSV _CLEANED.csv par fichier input
    """
    print_header("📋 WORKFLOW: NETTOYAGE DE DONNÉES")

    os.makedirs(output_dir, exist_ok=True)

    cleaned_files = []
    failed_files  = []

    for input_file in input_files:
        basename    = os.path.splitext(os.path.basename(input_file))[0]
        output_file = os.path.join(output_dir, f"{basename}_CLEANED.csv")

        cmd = ["python3", "data_cleaner.py", input_file, output_file]
        success, data = run_command(cmd, f"Nettoyage: {os.path.basename(input_file)}")

        if success and os.path.exists(output_file):
            cleaned_files.append(output_file)
            if data:
                rows = data.get('rows', '?')
                cols = data.get('columns', '?')
                null = data.get('null_remaining', '?')
                print(f"  📊 {rows} lignes × {cols} colonnes | {null} NULL restants")
        else:
            failed_files.append(input_file)

    # Résumé
    print_header("📋 RÉSUMÉ NETTOYAGE")
    print(f"  ✅ Nettoyés  : {len(cleaned_files)}/{len(input_files)} fichiers")
    print(f"  📂 Output    : {output_dir}/")
    if failed_files:
        print(f"  ❌ Échoués  : {[os.path.basename(f) for f in failed_files]}")

    return cleaned_files


def workflow_cross_reference_only(input_files, output_dir, rules_path=None, no_llm_enricher=False):
    """
    Workflow 2: Cross-référence seul
    → Premier fichier = fichier principal
    → Autres fichiers = références
    → Un CSV _ENRICHED.csv + rapport JSON par fichier principal
    """
    print_header("🔗 WORKFLOW: CROSS-RÉFÉRENCEMENT")

    if not input_files:
        print("  ❌ Aucun fichier fourni")
        return False

    main_file = input_files[0]
    ref_files = input_files[1:]

    print(f"  📂 Principal : {os.path.basename(main_file)}")
    if ref_files:
        print(f"  📚 Références: {[os.path.basename(r) for r in ref_files]}")
    else:
        print(f"  ℹ️  Mode simple (pas de références)")

    # Construire la commande
    cmd = ["python3", "cross_reference.py"] + input_files + ["--output", output_dir]

    if rules_path:
        cmd += ["--rules", rules_path]
    if no_llm_enricher:
        cmd += ["--no-llm-enricher"]

    success, data = run_command(cmd, f"Cross-reference: {os.path.basename(main_file)}")

    if success and data:
        print(f"\n  📊 Résultats:")
        print(f"     Lignes finales : {data.get('final_rows', '?')}")
        print(f"     Colonnes       : {data.get('final_cols', '?')}")
        print(f"     NULL restants  : {data.get('null_remaining', '?')}")
        print(f"     CSV            : {data.get('output_csv', '?')}")
        print(f"     Rapport        : {data.get('output_report', '?')}")

    return success


def workflow_full_pipeline(input_files, clean_dir, analysis_dir, rules_path=None, no_llm_enricher=False):
    """
    Workflow 3: Pipeline complet
    Étape 1 → Nettoyage de tous les fichiers
    Étape 2 → Cross-reference (premier fichier nettoyé = principal)
    """
    print_header("🚀 WORKFLOW: PIPELINE COMPLET")

    # ── Étape 1 : Nettoyage ──────────────────────────────────────────
    print(f"\n📍 ÉTAPE 1/2 — Nettoyage")
    cleaned_files = workflow_clean_only(input_files, clean_dir)

    if not cleaned_files:
        print("\n❌ Aucun fichier nettoyé — arrêt du pipeline")
        return False

    # ── Étape 2 : Cross-reference ─────────────────────────────────────
    print(f"\n📍 ÉTAPE 2/2 — Cross-référencement")
    success = workflow_cross_reference_only(
        cleaned_files,
        analysis_dir,
        rules_path=rules_path,
        no_llm_enricher=no_llm_enricher
    )

    if success:
        print_header("✅ PIPELINE COMPLET TERMINÉ")
        print(f"  📂 Nettoyés  : {clean_dir}/")
        print(f"  📊 Enrichis  : {analysis_dir}/")

    return success


def workflow_test_mode(no_llm_enricher=False):
    """
    Workflow 4: Mode test
    1. Génère des données de test via generate_test_data.py
    2. Lance le pipeline complet sur ces données
    """
    print_header("🧪 WORKFLOW: MODE TEST")

    # Générer les données de test
    print(f"\n📍 Generating test data...")
    cmd = ["python3", "generate_test_data.py"]
    success, _ = run_command(cmd, "Generating test data")

    if not success:
        print("❌ Échec génération données de test")
        return False

    # Lister les fichiers générés
    test_dir = "test_data"
    if not os.path.exists(test_dir):
        print(f"❌ Folder '{test_dir}' not found")
        return False

    test_files = sorted([
        os.path.join(test_dir, f)
        for f in os.listdir(test_dir)
        if f.endswith('.csv')
    ])

    if not test_files:
        print("❌ No test files generated")
        return False

    print(f"\n  📊 {len(test_files)} test files :")
    for f in test_files:
        print(f"     • {os.path.basename(f)}")

    # Lancer le pipeline complet
    timestamp    = datetime.now().strftime("%Y%m%d_%H%M%S")
    clean_dir    = f"test_cleaned_{timestamp}"
    analysis_dir = f"test_analysis_{timestamp}"

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
        description="Master Workflow — Cleaning and Cross Referencing",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Exemples:

  # Nettoyage seul :
  python3 workflow.py clean orders.csv products.csv --output cleaned/

  # Cross-référence seul (1er fichier = principal) :
  python3 workflow.py cross-ref orders_CLEANED.csv products.csv --output results/

  # Pipeline complet :
  python3 workflow.py full orders.csv products.csv --clean-dir cleaned/ --analysis-dir results/

  # Avec règles custom :
  python3 workflow.py full orders.csv products.csv --rules rules.json

  # Mode test :
  python3 workflow.py test
        """
    )

    parser.add_argument(
        'mode',
        choices=['clean', 'cross-ref', 'full', 'test'],
        help='Mode de workflow'
    )
    parser.add_argument(
        'files', nargs='*',
        help='Fichiers input (pour clean/cross-ref/full)'
    )
    parser.add_argument(
        '--output', default='output',
        help='Dossier de sortie (modes clean et cross-ref)'
    )
    parser.add_argument(
        '--clean-dir', default='cleaned_data',
        help='Dossier pour les fichiers nettoyés (mode full)'
    )
    parser.add_argument(
        '--analysis-dir', default='analysis_results',
        help='Dossier pour les résultats enrichis (mode full)'
    )
    parser.add_argument(
        '--rules', default=None,
        help='Chemin vers rules.json (règles de validation custom)'
    )
    parser.add_argument(
        '--no-llm-enricher', action='store_true',
        help='Désactiver le LLM Enricher (plus rapide, moins de tokens)'
    )

    args = parser.parse_args()

    # ── Vérifications ───────────────────────────────────────────────
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
        print(f"\n❌ rules.json file not found: {args.rules}")
        return 1

    # ── Header ──────────────────────────────────────────────────────
    print_header("🔧 DATA PROCESSING SUITE v2.0")
    print(f"  Mode    : {args.mode.upper()}")
    print(f"  Date    : {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    if args.files:
        print(f"  Files   : {[os.path.basename(f) for f in args.files]}")
    if args.rules:
        print(f"  Rules   : {args.rules}")

    # ── Exécution ───────────────────────────────────────────────────
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

    # ── Résultat final ──────────────────────────────────────────────
    print_header("✅ TERMINATED" if success else "❌ FAILED")
    return 0 if success else 1


if __name__ == "__main__":
    sys.exit(main())