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

def save_placeholder(outdir, filename, text):
    plt.figure(figsize=(10, 4))
    plt.text(0.5, 0.5, text, ha="center", va="center")
    plt.axis("off")
    plt.tight_layout()
    plt.savefig(os.path.join(outdir, filename), dpi=150)
    plt.close()


# Missing values bar chart
def plot_missing_bar(df, outdir, filename="missing_values.png") -> bool:
    missing = df.isna().sum()
    missing = missing[missing > 0].sort_values(ascending=False)

    if len(missing) == 0:
        save_placeholder(outdir, filename, "No missing values")
        return False

    plt.figure(figsize=(10, 5))
    plt.bar(missing.index.astype(str), missing.values)
    plt.xticks(rotation=60, ha="right")
    plt.title("Missing values per column")
    plt.ylabel("Missing count")
    plt.tight_layout()
    plt.savefig(os.path.join(outdir, filename), dpi=150)
    plt.close()
    return True


def plot_dtypes(df, outdir, filename="dtypes.png") -> bool:
    dtype_counts = df.dtypes.astype(str).value_counts()

    if dtype_counts.empty:
        save_placeholder(outdir, filename, "No columns")
        return False

    plt.figure(figsize=(7, 4))
    plt.bar(dtype_counts.index.astype(str), dtype_counts.values)
    plt.title("Column types")
    plt.ylabel("Number of columns")
    plt.xticks(rotation=30, ha="right")
    plt.tight_layout()
    plt.savefig(os.path.join(outdir, filename), dpi=150)
    plt.close()
    return True


def plot_numeric_histogram(df: pd.DataFrame, outdir: str, col: str | None, filename: str = "histogram.png"):

    """
    Histogram voor een numerieke kolom.

    - We zoeken eerst alle numerieke kolommen.
    - Als de user geen kolom kiest, nemen we de eerste numerieke kolom.
    - Als er geen numerieke kolommen zijn (of kolom is leeg) -> tekst in de plot.
    """
    numeric_cols = df.select_dtypes(include="number").columns.astype(str).tolist()

    plt.figure(figsize=(10, 5))

    if not numeric_cols:
        plt.text(0.5, 0.5, "No numeric columns", ha="center", va="center")
        plt.axis("off")
    else:
        if col is None or col not in numeric_cols:
            col = numeric_cols[0]

        series = df[col].dropna()
        if series.empty:
            plt.text(0.5, 0.5, f"No numeric data in '{col}'", ha="center", va="center")
            plt.axis("off")
        else:
            plt.hist(series, bins=10)
            plt.title(f"Histogram of {col}")
            plt.xlabel(col)
            plt.ylabel("Frequency")
            plt.tight_layout()

    plt.savefig(os.path.join(outdir, filename), dpi=150)
    plt.close()



# Lijn grafiek (eerste numerieke kolom)
def plot_line_chart(df: pd.DataFrame, outdir: str, col: str | None, filename: str = "line.png"):
    numeric_cols = df.select_dtypes(include="number").columns.astype(str).tolist()

    plt.figure(figsize=(10, 5))

    if not numeric_cols:
        plt.text(0.5, 0.5, "No numeric columns", ha="center", va="center")
        plt.axis("off")
    else:
        if col is None or col not in numeric_cols:
            col = numeric_cols[0]

        series = df[col]
        plt.plot(series.index, series.values)
        plt.title(f"Line chart of {col}")
        plt.xlabel("Row index")
        plt.ylabel(col)
        plt.tight_layout()

    plt.savefig(os.path.join(outdir, filename), dpi=150)
    plt.close()



# Taart grafiek (eerste tekst/categorie kolom)
def plot_pie_chart(df: pd.DataFrame, outdir: str, col: str | None, filename: str = "pie.png"):

    cat_cols = df.select_dtypes(include=["object", "category", "bool"]).columns.astype(str).tolist()

    plt.figure(figsize=(8, 6))

    if not cat_cols:
        plt.text(0.5, 0.5, "No categorical columns", ha="center", va="center")
        plt.axis("off")
    else:
        if col is None or col not in cat_cols:
            col = cat_cols[0]

        s = df[col].astype(str).fillna("NaN")
        counts = s.value_counts()

        if len(counts) > 10:
            top = counts.iloc[:10]
            rest = counts.iloc[10:].sum()
            counts = top.copy()
            counts["Other"] = rest

        plt.pie(counts.values, labels=counts.index.astype(str), autopct="%1.1f%%")
        plt.title(f"Pie chart of {col}")
        plt.tight_layout()

    plt.savefig(os.path.join(outdir, filename), dpi=150)
    plt.close()

# Summary bouwen
def build_summary(df: pd.DataFrame) -> dict:

    df_norm = df.copy()

    for c in df_norm.select_dtypes(include=["object", "category", "bool"]).columns:
        df_norm[c] = df_norm[c].astype(str).str.strip()

    numeric_cols = df.select_dtypes(include="number").columns.astype(str).tolist()
    cat_cols = df.select_dtypes(include=["object", "category", "bool"]).columns.astype(str).tolist()

    return {
        "rows": int(df.shape[0]),
        "columns": int(df.shape[1]),
        "missing_total": int(df.isna().sum().sum()),
        "duplicate_rows": int(df_norm.duplicated(keep=False).sum()),

        # nodig voor dropdowns in Blade
        "column_names": [str(c) for c in df.columns],
        "numeric_columns": numeric_cols,
        "categorical_columns": cat_cols,
    }

CHART_FILES = {
    "missing_values": "missing_values.png",
    "dtypes": "dtypes.png",
    "histogram": "histogram.png",
    "pie": "pie.png",
    "line": "line.png",
}

def parse_options() -> dict:
    # argv: visualize.py input outdir [options_json]
    if len(sys.argv) >= 4:
        try:
            return json.loads(sys.argv[3])
        except Exception:
            return {}
    return {}


def run_visualization(file_path, outdir):
    os.makedirs(outdir, exist_ok=True)

    df = read_dataset(file_path)
    summary = build_summary(df)

    options = parse_options()
    selected = options.get("charts") or ["missing_values", "dtypes"]
    chart_columns = options.get("chart_columns") or {}

    charts_out = {}

    if "missing_values" in selected:
        plot_missing_bar(df, outdir, CHART_FILES["missing_values"])
        charts_out["missing_values"] = CHART_FILES["missing_values"]

    if "dtypes" in selected:
        plot_dtypes(df, outdir, CHART_FILES["dtypes"])
        charts_out["dtypes"] = CHART_FILES["dtypes"]

    if "histogram" in selected:
        plot_numeric_histogram(df, outdir, chart_columns.get("histogram"), CHART_FILES["histogram"])
        charts_out["histogram"] = CHART_FILES["histogram"]

    if "pie" in selected:
        plot_pie_chart(df, outdir, chart_columns.get("pie"), CHART_FILES["pie"])
        charts_out["pie"] = CHART_FILES["pie"]

    if "line" in selected:
        plot_line_chart(df, outdir, chart_columns.get("line"), CHART_FILES["line"])
        charts_out["line"] = CHART_FILES["line"]

    summary["charts"] = charts_out
    summary["chart_columns"] = chart_columns

    with open(os.path.join(outdir, "summary.json"), "w", encoding="utf-8") as f:
        json.dump(summary, f, indent=2)

if __name__ == "__main__":
    if len(sys.argv) < 3:
        print("Usage: python visualize.py <input_file> <outdir>")
        sys.exit(1)

    run_visualization(sys.argv[1], sys.argv[2])
