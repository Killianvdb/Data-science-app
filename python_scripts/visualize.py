import os
import sys
import json
from pathlib import Path

import matplotlib
matplotlib.use("Agg")  # belangrijk voor server/headless
import matplotlib.pyplot as plt

import pandas as pd


# 1) Dataset lezen (CSV of Excel)
def read_dataset(path: Path) -> pd.DataFrame:
    path = Path(path)
    suffix = path.suffix.lower()

    if suffix == ".csv":
        # probeer simpel CSV te lezen
        return pd.read_csv(path)
    if suffix in [".xlsx", ".xls"]:
        return pd.read_excel(path)

    raise ValueError(f"Unsupported file type: {suffix}. Use CSV or Excel.")


# hier maken wij een "placeholder" afbeelding als er geen data is
def save_placeholder(outpath: Path, text: str):
    plt.figure(figsize=(8, 4))
    plt.text(0.5, 0.5, text, ha="center", va="center")
    plt.axis("off")
    plt.tight_layout()
    plt.savefig(outpath, dpi=150)
    plt.close()


# A) Missing values bar chart
def plot_missing_bar(df: pd.DataFrame, outdir: Path) -> str:
    outpath = outdir / "missing_bar.png"

    missing = df.isna().sum()
    missing = missing[missing > 0].sort_values(ascending=False)

    if len(missing) == 0:
        save_placeholder(outpath, "No missing values")
        return outpath.name

    plt.figure(figsize=(10, 5))
    plt.bar(missing.index.astype(str), missing.values)
    plt.xticks(rotation=60, ha="right")
    plt.title("Missing values per column")
    plt.ylabel("Missing count")
    plt.tight_layout()
    plt.savefig(outpath, dpi=150)
    plt.close()

    return outpath.name


# B) Lijn grafiek (eerste numerieke kolom)
def plot_line_chart(df: pd.DataFrame, outdir: Path) -> str:
    outpath = outdir / "line_chart.png"

    num_cols = list(df.select_dtypes(include="number").columns)
    if len(num_cols) == 0:
        save_placeholder(outpath, "No numeric columns for line chart")
        return outpath.name

    col = num_cols[0]
    series = df[col].dropna()

    if series.empty:
        save_placeholder(outpath, f"No numeric data in '{col}'")
        return outpath.name

    plt.figure(figsize=(10, 5))
    plt.plot(series.values)  # x = index, y = values
    plt.title(f"Line chart: {col}")
    plt.xlabel("Index")
    plt.ylabel(str(col))
    plt.tight_layout()
    plt.savefig(outpath, dpi=150)
    plt.close()

    return outpath.name


# C) Taart grafiek (eerste tekst/categorie kolom)
def plot_pie_chart(df: pd.DataFrame, outdir: Path) -> str:
    outpath = outdir / "pie_chart.png"

    cat_cols = list(df.select_dtypes(include="object").columns)
    if len(cat_cols) == 0:
        save_placeholder(outpath, "No categorical columns for pie chart")
        return outpath.name

    col = cat_cols[0]
    counts = df[col].astype(str).str.strip().value_counts()

    if counts.empty:
        save_placeholder(outpath, f"No categorical data in '{col}'")
        return outpath.name

    # neem top 6 en bundel de rest als "Other" (anders te druk)
    top = counts.head(6)
    rest = counts.iloc[6:].sum()
    if rest > 0:
        top["Other"] = rest

    plt.figure(figsize=(8, 6))
    plt.pie(top.values, labels=top.index.astype(str), autopct="%1.1f%%")
    plt.title(f"Pie chart: {col}")
    plt.tight_layout()
    plt.savefig(outpath, dpi=150)
    plt.close()

    return outpath.name


# Summary bouwen
def build_summary(df: pd.DataFrame) -> dict:
    # duplicate check (strip spaces in tekst)
    df_norm = df.copy()
    for c in df_norm.select_dtypes(include="object").columns:
        df_norm[c] = df_norm[c].astype(str).str.strip()

    return {
        "rows": int(df.shape[0]),
        "columns": int(df.shape[1]),
        "missing_total": int(df.isna().sum().sum()),
        "duplicate_rows": int(df_norm.duplicated(keep=False).sum()),
    }


def run_visualization(file_path: str, outdir: str):
    outdir = Path(outdir)
    outdir.mkdir(parents=True, exist_ok=True)

    df = read_dataset(Path(file_path))

    # 3 grafieken maken
    missing_file = plot_missing_bar(df, outdir)
    line_file = plot_line_chart(df, outdir)
    pie_file = plot_pie_chart(df, outdir)

    summary = build_summary(df)
    summary["charts"] = {
        "missing": missing_file,
        "line": line_file,
        "pie": pie_file,
    }

    with open(outdir / "summary.json", "w", encoding="utf-8") as f:
        json.dump(summary, f, indent=2)

    print(json.dumps({"status": "ok"}))


if __name__ == "__main__":
    if len(sys.argv) != 3:
        print("Usage: python visualize.py <input_file> <outdir>")
        sys.exit(1)

    run_visualization(sys.argv[1], sys.argv[2])
