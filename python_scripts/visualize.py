import json
import sys
from pathlib import Path

import matplotlib
matplotlib.use("Agg")  # headless/server safe
import matplotlib.pyplot as plt
import pandas as pd


# hier wordt de dataset ingelezen, afhankelijk van bestandstype (csv, excel)
def read_dataset(path: Path) -> pd.DataFrame:
    path = Path(path)
    suffix = path.suffix.lower()

    if suffix == ".csv":
        return pd.read_csv(path)
    if suffix in [".xlsx", ".xls"]:
        return pd.read_excel(path)

    raise ValueError(f"Unsupported file type: {suffix}. Use CSV or Excel.")


def save_placeholder(outpath: Path, text: str):
    plt.figure(figsize=(9, 4))
    plt.text(0.5, 0.5, text, ha="center", va="center")
    plt.axis("off")
    plt.tight_layout()
    plt.savefig(os.path.join(outdir, filename), dpi=150)
    plt.close()


def safe_cat_cols(df: pd.DataFrame):
    # pandas warning-proof: include string dtype too
    return df.select_dtypes(include=["object", "string", "category", "bool"]).columns.astype(str).tolist()


def safe_num_cols(df: pd.DataFrame):
    return df.select_dtypes(include=["number"]).columns.astype(str).tolist()


# Charts
def plot_missing_values_bar(df: pd.DataFrame, outdir: Path, filename: str) -> str:
    outpath = outdir / filename

    missing = df.isna().sum()
    missing = missing[missing > 0].sort_values(ascending=False)

    if missing.empty:
        save_placeholder(outpath, "No missing values")
        return outpath.name

    plt.figure(figsize=(10, 5))
    plt.bar(missing.index.astype(str), missing.values)
    plt.xticks(rotation=60, ha="right")
    plt.title("Missing values per column")
    plt.ylabel("Missing count")
    plt.tight_layout()
    plt.savefig(os.path.join(outdir, filename), dpi=150)
    plt.close()
    return outpath.name


def plot_histogram(df: pd.DataFrame, outdir: Path, col: str | None, filename: str) -> str:
    outpath = outdir / filename
    numeric_cols = safe_num_cols(df)

    if not numeric_cols:
        save_placeholder(outpath, "No numeric columns for histogram")
        return outpath.name

    if col is None or col not in numeric_cols:
        col = numeric_cols[0]

    s = df[col].dropna()
    if s.empty:
        save_placeholder(outpath, f"No numeric data in '{col}'")
        return outpath.name

    plt.figure(figsize=(10, 5))
    plt.hist(s, bins=12)
    plt.title(f"Histogram: {col}")
    plt.xlabel(col)
    plt.ylabel("Frequency")
    plt.tight_layout()
    plt.savefig(outpath, dpi=150)
    plt.close()
    return outpath.name


def plot_line(df: pd.DataFrame, outdir: Path, col: str | None, filename: str) -> str:
    outpath = outdir / filename
    numeric_cols = safe_num_cols(df)

    if not numeric_cols:
        save_placeholder(outpath, "No numeric columns for line chart")
        return outpath.name

    if col is None or col not in numeric_cols:
        col = numeric_cols[0]

    s = df[col]
    if s.dropna().empty:
        save_placeholder(outpath, f"No numeric data in '{col}'")
        return outpath.name

    plt.figure(figsize=(10, 5))
    plt.plot(s.index, s.values)
    plt.title(f"Line chart: {col}")
    plt.xlabel("Row index")
    plt.ylabel(col)
    plt.tight_layout()
    plt.savefig(outpath, dpi=150)
    plt.close()
    return outpath.name


def plot_category_bar(df: pd.DataFrame, outdir: Path, col: str | None, filename: str) -> str:
    outpath = outdir / filename
    cat_cols = safe_cat_cols(df)

    if not cat_cols:
        save_placeholder(outpath, "No categorical columns for category bar chart")
        return outpath.name

    if col is None or col not in cat_cols:
        col = cat_cols[0]

    s = df[col].astype(str).fillna("NaN").str.strip()
    counts = s.value_counts()

    if counts.empty:
        save_placeholder(outpath, f"No categorical data in '{col}'")
        return outpath.name

    # keep it readable
    counts = counts.head(12)

    plt.figure(figsize=(10, 5))
    plt.bar(counts.index.astype(str), counts.values)
    plt.xticks(rotation=45, ha="right")
    plt.title(f"Category bar: {col}")
    plt.ylabel("Count")
    plt.tight_layout()
    plt.savefig(outpath, dpi=150)
    plt.close()
    return outpath.name


# Summary + Recommendations
CHART_FILES = {
    "missing_values": "missing_values.png",
    "histogram": "histogram.png",
    "line": "line.png",
    "category_bar": "category_bar.png",
}

CHART_LABELS = {
    "missing_values": "Missing values (bar)",
    "histogram": "Histogram",
    "line": "Line chart",
    "category_bar": "Category bar chart",
}


def build_summary(df: pd.DataFrame) -> dict:
    # duplicate check: strip text-like columns
    df_norm = df.copy()
    for c in safe_cat_cols(df_norm):
        # only if column exists
        if c in df_norm.columns.astype(str).tolist():
            pass

    # safer: iterate original df columns and strip if cat-like
    for c in df_norm.select_dtypes(include=["object", "string", "category", "bool"]).columns:
        df_norm[c] = df_norm[c].astype(str).str.strip()

    numeric_cols = safe_num_cols(df)
    cat_cols = safe_cat_cols(df)

    return {
        "rows": int(df.shape[0]),
        "columns": int(df.shape[1]),
        "missing_total": int(df.isna().sum().sum()),
        "duplicate_rows": int(df_norm.duplicated(keep=False).sum()),
        "numeric_columns": numeric_cols,
        "categorical_columns": cat_cols,
        "column_names": [str(c) for c in df.columns],
    }


def pick_best_category_col(df: pd.DataFrame, cat_cols: list[str]) -> str | None:
    if not cat_cols:
        return None

    best = None
    best_n = None

    for c in cat_cols:
        s = df[c].astype(str).fillna("NaN").str.strip()
        n = int(s.nunique(dropna=False))
        # liever niet te veel unieke waarden
        if best is None or n < best_n:
            best = c
            best_n = n

    return best


def recommend_and_autoselect(df: pd.DataFrame, summary: dict) -> tuple[list[dict], list[str], dict]:
    """
    Geeft terug:
    - recommendations: lijst met {key,label,reason,default_col?}
    - auto_selected: lijst met chart keys (default selectie)
    - default_chart_columns: dict met gekozen kolommen voor charts
    """
    recs = []
    auto_selected = []
    chart_columns = {}

    missing_total = summary["missing_total"]
    numeric_cols = summary["numeric_columns"]
    cat_cols = summary["categorical_columns"]

    # Missing values: toon enkel als er missing is (of maak dit "altijd")
    if missing_total > 0:
        recs.append({
            "key": "missing_values",
            "label": CHART_LABELS["missing_values"],
            "reason": f"Missing total = {missing_total}",
        })
        auto_selected.append("missing_values")

    # Histogram: als numeric cols
    if numeric_cols:
        default_num = numeric_cols[0]
        recs.append({
            "key": "histogram",
            "label": CHART_LABELS["histogram"],
            "reason": f"Numeric columns: {len(numeric_cols)}",
            "default_col": default_num,
        })
        auto_selected.append("histogram")
        chart_columns["histogram"] = default_num

        # Line: vaak nuttig maar minder prioriteit -> alleen toevoegen als je wil
        default_line = numeric_cols[0]
        recs.append({
            "key": "line",
            "label": CHART_LABELS["line"],
            "reason": "Trend view on a numeric column",
            "default_col": default_line,
        })
        # auto_selected.append("line")
        chart_columns["line"] = default_line

    # Category bar: als categorical cols
    if cat_cols:
        best_cat = pick_best_category_col(df, cat_cols) or cat_cols[0]
        recs.append({
            "key": "category_bar",
            "label": CHART_LABELS["category_bar"],
            "reason": f"Categorical columns: {len(cat_cols)}",
            "default_col": best_cat,
        })
        auto_selected.append("category_bar")
        chart_columns["category_bar"] = best_cat

    # fallback: als alles leeg is, toon tenminste dtypes? (maar jij gebruikt dtypes niet meer)
    if not auto_selected:
        # show missing anyway
        recs.append({
            "key": "missing_values",
            "label": CHART_LABELS["missing_values"],
            "reason": "Fallback chart",
        })
        auto_selected.append("missing_values")

    return recs, auto_selected, chart_columns


def parse_options() -> dict:
    # argv: visualize.py input outdir [options_json]
    if len(sys.argv) >= 4:
        try:
            return json.loads(sys.argv[3])
        except Exception:
            return {}
    return {}


# Main
def run_visualization(file_path: str, outdir: str):
    outdir = Path(outdir)
    outdir.mkdir(parents=True, exist_ok=True)

    df = read_dataset(Path(file_path))
    summary = build_summary(df)

    # recommendations + auto-select
    recs, auto_selected, default_cols = recommend_and_autoselect(df, summary)

    # user options
    options = parse_options()
    requested_charts = options.get("charts")  # list or None
    requested_cols = options.get("chart_columns") or {}

    # selectie:
    selected = requested_charts if (isinstance(requested_charts, list) and len(requested_charts) > 0) else auto_selected

    # kolommen:
    chart_columns = default_cols.copy()
    chart_columns.update({k: v for k, v in requested_cols.items() if v})

    charts_out = {}

    if "missing_values" in selected:
        charts_out["missing_values"] = plot_missing_values_bar(df, outdir, CHART_FILES["missing_values"])

    if "histogram" in selected:
        charts_out["histogram"] = plot_histogram(df, outdir, chart_columns.get("histogram"), CHART_FILES["histogram"])

    if "line" in selected:
        charts_out["line"] = plot_line(df, outdir, chart_columns.get("line"), CHART_FILES["line"])

    if "category_bar" in selected:
        charts_out["category_bar"] = plot_category_bar(df, outdir, chart_columns.get("category_bar"), CHART_FILES["category_bar"])

    summary["charts"] = charts_out
    summary["recommendations"] = recs
    summary["auto_selected"] = auto_selected
    summary["selected_charts"] = selected
    summary["chart_columns"] = chart_columns

    with open(outdir / "summary.json", "w", encoding="utf-8") as f:
        json.dump(summary, f, indent=2)

if __name__ == "__main__":
    if len(sys.argv) < 3:
        print("Usage: python visualize.py <input_file> <outdir> [options_json]")
        sys.exit(1)

    run_visualization(sys.argv[1], sys.argv[2])
