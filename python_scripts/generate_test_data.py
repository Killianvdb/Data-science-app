#!/usr/bin/env python3
"""
GENERATE TEST DATA v1.0
========================
Génère des datasets de test réalistes pour valider le pipeline complet.

Fichiers générés dans test_data/ :
  orders.csv    → fichier principal avec NULL, erreurs, incohérences
  products.csv  → référence produits (pour cross-reference)
  customers.csv → référence clients (pour cross-reference)

Les données contiennent intentionnellement :
  - Valeurs NULL
  - Prix négatifs
  - Dates mal formatées
  - Catégories manquantes (à remplir via cross-reference)
  - Violations de règles métier (delivery < order date)
"""

import os
import random
import pandas as pd
import numpy as np
from datetime import datetime, timedelta

random.seed(42)
np.random.seed(42)

OUTPUT_DIR = "test_data"

# ============================================================================
# HELPERS
# ============================================================================

def random_date(start_year=2023, end_year=2024):
    start = datetime(start_year, 1, 1)
    end   = datetime(end_year, 12, 31)
    delta = end - start
    return start + timedelta(days=random.randint(0, delta.days))

def introduce_nulls(series, pct=0.15):
    """Introduit des NULL aléatoirement"""
    mask = np.random.random(len(series)) < pct
    result = series.copy().astype(object)
    result[mask] = np.nan
    return result

def introduce_errors(series, pct=0.05, error_fn=None):
    """Introduit des erreurs aléatoirement"""
    mask = np.random.random(len(series)) < pct
    result = series.copy()
    if error_fn:
        result[mask] = result[mask].apply(error_fn)
    return result

# ============================================================================
# PRODUCTS (référence)
# ============================================================================

def generate_products():
    """Référence produits — données propres"""

    products = [
        ("Laptop Pro 15",     "Electronics",  1299.99, "LPT-001"),
        ("Laptop Air 13",     "Electronics",   999.99, "LPT-002"),
        ("MacBook Pro",       "Electronics",  1899.99, "MBP-001"),
        ("Wireless Mouse",    "Accessories",    29.99, "MSE-001"),
        ("Mechanical Keyboard","Accessories",   89.99, "KBD-001"),
        ("USB-C Hub",         "Accessories",    49.99, "HUB-001"),
        ("Monitor 27inch",    "Electronics",   399.99, "MON-001"),
        ("Webcam HD",         "Electronics",    79.99, "CAM-001"),
        ("Desk Lamp",         "Office",         39.99, "LMP-001"),
        ("Office Chair",      "Furniture",     299.99, "CHR-001"),
        ("Standing Desk",     "Furniture",     599.99, "DSK-001"),
        ("Notebook A5",       "Stationery",      4.99, "NTB-001"),
        ("Pen Pack x10",      "Stationery",      8.99, "PEN-001"),
        ("Headphones Pro",    "Electronics",   199.99, "HPH-001"),
        ("Smartphone X12",    "Electronics",   799.99, "SPH-001"),
    ]

    df = pd.DataFrame(products, columns=["Product", "Category", "UnitPrice", "SKU"])
    df["Stock"] = np.random.randint(0, 500, len(df))
    df["Supplier"] = random.choices(["SupplierA", "SupplierB", "SupplierC"], k=len(df))

    return df

# ============================================================================
# CUSTOMERS (référence)
# ============================================================================

def generate_customers():
    """Référence clients — données propres"""

    first_names = ["Alice", "Bob", "Charlie", "Diana", "Eve",
                   "Frank", "Grace", "Henry", "Iris", "Jack"]
    last_names  = ["Smith", "Johnson", "Williams", "Brown", "Jones",
                   "Garcia", "Miller", "Davis", "Wilson", "Taylor"]
    cities      = ["Paris", "Lyon", "Marseille", "Toulouse", "Nice",
                   "Nantes", "Strasbourg", "Bordeaux", "Lille", "Rennes"]
    segments    = ["Premium", "Standard", "Basic"]

    n = 50
    customer_ids = [f"CUST-{str(i).zfill(4)}" for i in range(1, n+1)]
    names        = [f"{random.choice(first_names)} {random.choice(last_names)}" for _ in range(n)]

    df = pd.DataFrame({
        "CustomerID": customer_ids,
        "CustomerName": names,
        "City": random.choices(cities, k=n),
        "Segment": random.choices(segments, weights=[20, 50, 30], k=n),
        "JoinDate": [random_date(2020, 2022).strftime("%Y-%m-%d") for _ in range(n)],
        "Email": [f"customer{i}@example.com" for i in range(1, n+1)]
    })

    return df

# ============================================================================
# ORDERS (fichier principal — avec erreurs intentionnelles)
# ============================================================================

def generate_orders(products_df, customers_df):
    """
    Fichier principal avec erreurs intentionnelles :
    - Category NULL (à remplir via products)
    - Prix négatifs
    - Dates de livraison avant commande
    - CustomerName NULL (à remplir via customers)
    - Quelques doublons
    """

    n = 200
    product_names  = products_df["Product"].tolist()
    customer_ids   = customers_df["CustomerID"].tolist()
    customer_names = dict(zip(customers_df["CustomerID"], customers_df["CustomerName"]))

    order_ids    = [f"ORD-{str(i).zfill(5)}" for i in range(1, n+1)]
    cust_ids     = random.choices(customer_ids, k=n)
    cust_names   = [customer_names[c] for c in cust_ids]
    products     = random.choices(product_names, k=n)
    quantities   = np.random.randint(1, 20, n)
    unit_prices  = [
        float(products_df.loc[products_df["Product"] == p, "UnitPrice"].values[0])
        for p in products
    ]
    categories   = [
        products_df.loc[products_df["Product"] == p, "Category"].values[0]
        for p in products
    ]
    order_dates    = [random_date(2024, 2024) for _ in range(n)]
    delivery_dates = [d + timedelta(days=random.randint(1, 14)) for d in order_dates]
    totals         = [q * p for q, p in zip(quantities, unit_prices)]

    df = pd.DataFrame({
        "OrderID":        order_ids,
        "CustomerID":     cust_ids,
        "CustomerName":   cust_names,
        "Product":        products,
        "Category":       categories,
        "Quantity":       quantities,
        "UnitPrice":      unit_prices,
        "TotalAmount":    totals,
        "OrderDate":      [d.strftime("%Y-%m-%d") for d in order_dates],
        "DeliveryDate":   [d.strftime("%Y-%m-%d") for d in delivery_dates],
        "Status":         random.choices(["Pending", "Shipped", "Delivered", "Cancelled"],
                                         weights=[20, 30, 40, 10], k=n)
    })

    # ── Introduire des erreurs intentionnelles ──────────────────────

    # 1. Category NULL (~20% des lignes) — à remplir via products.csv
    null_mask = np.random.random(n) < 0.20
    df.loc[null_mask, "Category"] = np.nan

    # 2. CustomerName NULL (~10%) — à remplir via customers.csv
    null_mask2 = np.random.random(n) < 0.10
    df.loc[null_mask2, "CustomerName"] = np.nan

    # 3. Prix négatifs (~5%) — erreur de saisie
    neg_mask = np.random.random(n) < 0.05
    df.loc[neg_mask, "UnitPrice"]   = -df.loc[neg_mask, "UnitPrice"]
    df.loc[neg_mask, "TotalAmount"] = -df.loc[neg_mask, "TotalAmount"]

    # 4. Delivery avant Order (~3%) — erreur métier
    bad_date_mask = np.random.random(n) < 0.03
    bad_indices = df[bad_date_mask].index
    for idx in bad_indices:
        order_dt = datetime.strptime(df.at[idx, "OrderDate"], "%Y-%m-%d")
        df.at[idx, "DeliveryDate"] = (order_dt - timedelta(days=random.randint(1, 5))).strftime("%Y-%m-%d")

    # 5. Quantity NULL (~8%)
    qty_null_mask = np.random.random(n) < 0.08
    df.loc[qty_null_mask, "Quantity"] = np.nan

    # 6. Format de date mixte (~5% en DD/MM/YYYY)
    date_format_mask = np.random.random(n) < 0.05
    for idx in df[date_format_mask].index:
        d = datetime.strptime(df.at[idx, "OrderDate"], "%Y-%m-%d")
        df.at[idx, "OrderDate"] = d.strftime("%d/%m/%Y")

    # 7. Doublons (~3 lignes)
    duplicate_rows = df.sample(3)
    df = pd.concat([df, duplicate_rows], ignore_index=True)

    # 8. TotalAmount incohérent (~5%) — recalcul nécessaire
    incoherent_mask = np.random.random(len(df)) < 0.05
    df.loc[incoherent_mask, "TotalAmount"] = df.loc[incoherent_mask, "TotalAmount"] * random.uniform(0.5, 2.0)

    return df

# ============================================================================
# RULES.JSON (exemple de règles custom)
# ============================================================================

def generate_rules_json():
    """Génère un rules.json d'exemple"""
    rules = {
        "description": "Règles métier pour le dataset orders",
        "rules": [
            {
                "rule_id": "price_positive",
                "description": "UnitPrice doit être positif",
                "type": "range",
                "column": "UnitPrice",
                "condition": "df['UnitPrice'] < 0",
                "fix_action": "abs",
                "fix_value": None
            },
            {
                "rule_id": "total_positive",
                "description": "TotalAmount doit être positif",
                "type": "range",
                "column": "TotalAmount",
                "condition": "df['TotalAmount'] < 0",
                "fix_action": "abs",
                "fix_value": None
            },
            {
                "rule_id": "delivery_after_order",
                "description": "DeliveryDate doit être >= OrderDate",
                "type": "cross_column",
                "column": "DeliveryDate",
                "condition": "pd.to_datetime(df['DeliveryDate'], errors='coerce') < pd.to_datetime(df['OrderDate'], errors='coerce')",
                "fix_action": "flag",
                "fix_value": None
            },
            {
                "rule_id": "quantity_positive",
                "description": "Quantity doit être > 0",
                "type": "range",
                "column": "Quantity",
                "condition": "df['Quantity'].notna() & (df['Quantity'] <= 0)",
                "fix_action": "null",
                "fix_value": None
            }
        ]
    }
    return rules

# ============================================================================
# MAIN
# ============================================================================

def main():
    print(f"\n{'='*60}")
    print(f"🧪 GÉNÉRATION DES DONNÉES DE TEST")
    print(f"{'='*60}")

    os.makedirs(OUTPUT_DIR, exist_ok=True)

    # Générer les datasets
    print(f"\n📊 Génération des datasets...")

    products_df  = generate_products()
    customers_df = generate_customers()
    orders_df    = generate_orders(products_df, customers_df)
    rules        = generate_rules_json()

    # Sauvegarder
    products_path  = os.path.join(OUTPUT_DIR, "products.csv")
    customers_path = os.path.join(OUTPUT_DIR, "customers.csv")
    orders_path    = os.path.join(OUTPUT_DIR, "orders.csv")
    rules_path     = os.path.join(OUTPUT_DIR, "rules.json")

    products_df.to_csv(products_path,   index=False)
    customers_df.to_csv(customers_path, index=False)
    orders_df.to_csv(orders_path,       index=False)

    with open(rules_path, 'w') as f:
        import json
        json.dump(rules, f, indent=2)

    # Résumé
    print(f"\n✅ Fichiers générés dans '{OUTPUT_DIR}/':")
    print(f"   📦 products.csv   → {len(products_df)} produits (référence propre)")
    print(f"   👥 customers.csv  → {len(customers_df)} clients  (référence propre)")
    print(f"   🛒 orders.csv     → {len(orders_df)} commandes (avec erreurs intentionnelles)")
    print(f"   📋 rules.json     → règles custom de validation")

    print(f"\n⚠️  Erreurs intentionnelles dans orders.csv:")
    print(f"   • ~20% Category NULL        → à remplir via products.csv")
    print(f"   • ~10% CustomerName NULL    → à remplir via customers.csv")
    print(f"   •  ~5% Prix négatifs        → à corriger (valeur absolue)")
    print(f"   •  ~3% DeliveryDate < OrderDate → violation métier")
    print(f"   •  ~8% Quantity NULL        → à imputer")
    print(f"   •  ~5% Dates mal formatées  → à normaliser (DD/MM/YYYY)")
    print(f"   •    3 Doublons             → à supprimer")

    print(f"\n🚀 Pour tester le pipeline complet:")
    print(f"   python3 workflow.py full test_data/orders.csv test_data/products.csv test_data/customers.csv \\")
    print(f"     --clean-dir test_cleaned/ --analysis-dir test_results/ \\")
    print(f"     --rules test_data/rules.json")

if __name__ == "__main__":
    main()