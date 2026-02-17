#!/usr/bin/env python3
"""
Script de comparaison AVANT/APRÈS nettoyage
Compare les fichiers originaux et nettoyés
"""
import pandas as pd
import os
import sys
from pathlib import Path

def compare_files(original_path, cleaned_path):
    """Compare un fichier original et nettoyé"""
    
    filename = os.path.basename(original_path).replace('.csv', '')
    
    print(f"\n{'='*80}")
    print(f"📄 COMPARAISON : {filename}")
    print(f"{'='*80}")
    
    # Charger les fichiers
    try:
        df_orig = pd.read_csv(original_path)
        df_clean = pd.read_csv(cleaned_path)
    except Exception as e:
        print(f"❌ Erreur de chargement: {e}")
        return
    
    # 1. STATISTIQUES GLOBALES
    print(f"\n📊 STATISTIQUES GLOBALES:")
    print(f"{'':25} {'AVANT':>15} {'APRÈS':>15} {'CHANGEMENT':>15}")
    print(f"{'-'*72}")
    print(f"{'Lignes':25} {len(df_orig):>15,} {len(df_clean):>15,} {len(df_clean)-len(df_orig):>+15,}")
    print(f"{'Colonnes':25} {len(df_orig.columns):>15} {len(df_clean.columns):>15} {len(df_clean.columns)-len(df_orig.columns):>+15}")
    
    # 2. COMPARAISON PAR COLONNE
    print(f"\n📋 COMPARAISON PAR COLONNE:")
    print(f"{'-'*80}")
    
    for col in df_orig.columns:
        if col not in df_clean.columns:
            print(f"\n❌ Colonne '{col}' supprimée !")
            continue
        
        print(f"\n🔹 {col}")
        print(f"   Type: {df_orig[col].dtype} → {df_clean[col].dtype}")
        
        # NULL
        null_orig = df_orig[col].isna().sum()
        null_clean = df_clean[col].isna().sum()
        if null_orig != null_clean:
            print(f"   NULL: {null_orig} → {null_clean} ({null_clean - null_orig:+d})")
        
        # Pour colonnes numériques
        if pd.api.types.is_numeric_dtype(df_orig[col]) and pd.api.types.is_numeric_dtype(df_clean[col]):
            # Valeurs négatives
            neg_orig = (df_orig[col] < 0).sum()
            neg_clean = (df_clean[col] < 0).sum()
            if neg_orig != neg_clean:
                print(f"   Négatifs: {neg_orig} → {neg_clean} ({neg_clean - neg_orig:+d})")
            
            # Min/Max
            if not df_orig[col].dropna().empty and not df_clean[col].dropna().empty:
                min_orig = df_orig[col].min()
                max_orig = df_orig[col].max()
                min_clean = df_clean[col].min()
                max_clean = df_clean[col].max()
                
                if min_orig != min_clean or max_orig != max_clean:
                    print(f"   Range: [{min_orig:.2f}, {max_orig:.2f}] → [{min_clean:.2f}, {max_clean:.2f}]")
        
        # Pour colonnes de dates
        if col.lower().find('date') >= 0 or col.lower().find('birthday') >= 0:
            # Échantillon de valeurs
            sample_orig = df_orig[col].dropna().head(3).tolist()
            sample_clean = df_clean[col].dropna().head(3).tolist()
            
            if sample_orig != sample_clean:
                print(f"   Échantillon AVANT: {sample_orig}")
                print(f"   Échantillon APRÈS: {sample_clean}")
        
        # Pour colonnes catégorielles
        if df_orig[col].dtype == 'object':
            unique_orig = df_orig[col].nunique()
            unique_clean = df_clean[col].nunique()
            if unique_orig != unique_clean:
                print(f"   Valeurs uniques: {unique_orig} → {unique_clean}")
    
    # 3. EXEMPLES DE MODIFICATIONS
    print(f"\n📝 EXEMPLES DE MODIFICATIONS:")
    print(f"{'-'*80}")
    
    changes_found = False
    
    for col in df_orig.columns:
        if col not in df_clean.columns:
            continue
        
        # Trouver les lignes où les valeurs ont changé
        try:
            mask = df_orig[col].astype(str) != df_clean[col].astype(str)
            changed_rows = df_orig[mask].head(5)
            
            if len(changed_rows) > 0:
                changes_found = True
                print(f"\n🔸 Colonne '{col}' : {mask.sum()} valeurs modifiées")
                print(f"   Exemples (5 premières modifications):")
                
                for idx in changed_rows.index[:5]:
                    orig_val = df_orig.loc[idx, col]
                    clean_val = df_clean.loc[idx, col]
                    print(f"     Ligne {idx}: {repr(orig_val)} → {repr(clean_val)}")
        except:
            pass
    
    if not changes_found:
        print("   ✅ Aucune modification de valeur détectée (seulement format)")

def compare_all_files(original_dir, cleaned_dir):
    """Compare tous les fichiers"""
    
    print(f"\n{'#'*80}")
    print(f"# COMPARAISON AVANT/APRÈS NETTOYAGE")
    print(f"{'#'*80}")
    print(f"\n📂 Dossier original: {original_dir}")
    print(f"📂 Dossier nettoyé: {cleaned_dir}")
    
    # Lister les fichiers originaux
    original_files = sorted([f for f in os.listdir(original_dir) if f.endswith('.csv')])
    
    if not original_files:
        print(f"\n❌ Aucun fichier CSV trouvé dans {original_dir}")
        return
    
    print(f"\n📁 {len(original_files)} fichiers trouvés")
    
    # Comparer chaque fichier
    for orig_file in original_files:
        orig_path = os.path.join(original_dir, orig_file)
        
        # Trouver le fichier nettoyé correspondant
        base_name = orig_file.replace('.csv', '')
        clean_file = f"{base_name}_CLEANED.csv"
        clean_path = os.path.join(cleaned_dir, clean_file)
        
        if not os.path.exists(clean_path):
            print(f"\n⚠️  Fichier nettoyé non trouvé: {clean_file}")
            continue
        
        compare_files(orig_path, clean_path)
    
    # Résumé global
    print(f"\n{'='*80}")
    print(f"✅ COMPARAISON TERMINÉE")
    print(f"{'='*80}\n")

def generate_html_report(original_dir, cleaned_dir, output_file):
    """Génère un rapport HTML de comparaison"""
    
    html = """
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Rapport de Nettoyage de Données</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
        h2 { color: #34495e; margin-top: 30px; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; background: white; }
        th { background: #3498db; color: white; padding: 12px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #ddd; }
        tr:hover { background: #f8f9fa; }
        .stat { background: #e8f4f8; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .change-pos { color: #27ae60; font-weight: bold; }
        .change-neg { color: #e74c3c; font-weight: bold; }
        .file-section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <h1>📊 Rapport de Nettoyage de Données</h1>
"""
    
    original_files = sorted([f for f in os.listdir(original_dir) if f.endswith('.csv')])
    
    for orig_file in original_files:
        orig_path = os.path.join(original_dir, orig_file)
        base_name = orig_file.replace('.csv', '')
        clean_file = f"{base_name}_CLEANED.csv"
        clean_path = os.path.join(cleaned_dir, clean_file)
        
        if not os.path.exists(clean_path):
            continue
        
        try:
            df_orig = pd.read_csv(orig_path)
            df_clean = pd.read_csv(clean_path)
        except:
            continue
        
        html += f"""
    <div class="file-section">
        <h2>📄 {base_name}</h2>
        
        <div class="stat">
            <strong>Lignes:</strong> {len(df_orig):,} → {len(df_clean):,} 
            <span class="{'change-pos' if len(df_clean) >= len(df_orig) else 'change-neg'}">
                ({len(df_clean) - len(df_orig):+,})
            </span>
        </div>
        
        <table>
            <tr>
                <th>Colonne</th>
                <th>Type</th>
                <th>NULL Avant</th>
                <th>NULL Après</th>
                <th>Changement</th>
            </tr>
"""
        
        for col in df_orig.columns:
            if col not in df_clean.columns:
                continue
            
            null_orig = df_orig[col].isna().sum()
            null_clean = df_clean[col].isna().sum()
            change = null_clean - null_orig
            change_class = 'change-pos' if change > 0 else 'change-neg' if change < 0 else ''
            
            html += f"""
            <tr>
                <td><strong>{col}</strong></td>
                <td>{df_clean[col].dtype}</td>
                <td>{null_orig}</td>
                <td>{null_clean}</td>
                <td class="{change_class}">{change:+d}</td>
            </tr>
"""
        
        html += """
        </table>
    </div>
"""
    
    html += """
</body>
</html>
"""
    
    with open(output_file, 'w', encoding='utf-8') as f:
        f.write(html)
    
    print(f"\n📄 Rapport HTML généré: {output_file}")

if __name__ == "__main__":
    # Chemins par défaut
    original_dir = "my_data"
    cleaned_dir = "cleaned_data"
    
    # Permettre de passer des chemins en argument
    if len(sys.argv) >= 3:
        original_dir = sys.argv[1]
        cleaned_dir = sys.argv[2]
    
    # Vérifier que les dossiers existent
    if not os.path.exists(original_dir):
        print(f"❌ Dossier original non trouvé: {original_dir}")
        sys.exit(1)
    
    if not os.path.exists(cleaned_dir):
        print(f"❌ Dossier nettoyé non trouvé: {cleaned_dir}")
        sys.exit(1)
    
    # Comparaison texte
    compare_all_files(original_dir, cleaned_dir)
    
    # Rapport HTML
    output_html = "comparison_report.html"
    generate_html_report(original_dir, cleaned_dir, output_html)
    
    print(f"💾 Rapport sauvegardé: {output_html}")
    print(f"🌐 Ouvre ce fichier dans un navigateur pour voir le rapport détaillé")