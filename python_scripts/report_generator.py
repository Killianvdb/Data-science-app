#!/usr/bin/env python3
"""
REPORT GENERATOR - v1.0
========================
Reads the _REPORT.json produced by cross_reference.py and generates
a professional PDF report summarising what the pipeline changed.

Usage:
  python3 report_generator.py <report_json_path> <output_pdf_path>

Output sections:
  1. Executive Summary   — headline numbers at a glance
  2. Cross-Reference     — which reference files were joined and how
  3. Validation          — rules that fired, flags raised, values fixed
  4. Derived Columns     — formulas detected and values recalculated
  5. Enrichment          — LLM-predicted values per column
  6. Final State         — row/column counts, NULLs remaining
"""

import sys
import json
import os
from datetime import datetime

from reportlab.lib.pagesizes import letter
from reportlab.lib import colors
from reportlab.lib.units import inch
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.enums import TA_LEFT, TA_CENTER, TA_RIGHT
from reportlab.platypus import (
    SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle,
    HRFlowable, PageBreak, KeepTogether
)
from reportlab.platypus import ListFlowable, ListItem


# ============================================================================
# COLOUR PALETTE
# ============================================================================

C_DARK    = colors.HexColor('#1E293B')   # headings
C_BLUE    = colors.HexColor('#2563EB')   # accent / section headers
C_LIGHT   = colors.HexColor('#EFF6FF')   # table header bg
C_SUCCESS = colors.HexColor('#16A34A')   # green — rows kept, success
C_WARN    = colors.HexColor('#D97706')   # amber — flags / warnings
C_DANGER  = colors.HexColor('#DC2626')   # red   — errors / drops
C_MUTED   = colors.HexColor('#64748B')   # secondary text
C_BORDER  = colors.HexColor('#CBD5E1')   # table borders
C_WHITE   = colors.white


# ============================================================================
# STYLES
# ============================================================================

def build_styles():
    base = getSampleStyleSheet()

    styles = {
        'title': ParagraphStyle(
            'ReportTitle',
            fontName='Helvetica-Bold',
            fontSize=22,
            textColor=C_DARK,
            spaceAfter=4,
            alignment=TA_LEFT,
        ),
        'subtitle': ParagraphStyle(
            'ReportSubtitle',
            fontName='Helvetica',
            fontSize=10,
            textColor=C_MUTED,
            spaceAfter=20,
            alignment=TA_LEFT,
        ),
        'section': ParagraphStyle(
            'SectionHeader',
            fontName='Helvetica-Bold',
            fontSize=13,
            textColor=C_BLUE,
            spaceBefore=18,
            spaceAfter=6,
        ),
        'subsection': ParagraphStyle(
            'SubsectionHeader',
            fontName='Helvetica-Bold',
            fontSize=10,
            textColor=C_DARK,
            spaceBefore=10,
            spaceAfter=4,
        ),
        'body': ParagraphStyle(
            'Body',
            fontName='Helvetica',
            fontSize=9,
            textColor=C_DARK,
            spaceAfter=4,
            leading=14,
        ),
        'muted': ParagraphStyle(
            'Muted',
            fontName='Helvetica',
            fontSize=8,
            textColor=C_MUTED,
            spaceAfter=4,
            leading=12,
        ),
        'table_header': ParagraphStyle(
            'TableHeader',
            fontName='Helvetica-Bold',
            fontSize=8,
            textColor=C_WHITE,
            alignment=TA_LEFT,
        ),
        'table_cell': ParagraphStyle(
            'TableCell',
            fontName='Helvetica',
            fontSize=8,
            textColor=C_DARK,
            leading=11,
        ),
        'table_cell_muted': ParagraphStyle(
            'TableCellMuted',
            fontName='Helvetica',
            fontSize=8,
            textColor=C_MUTED,
            leading=11,
        ),
        'stat_value': ParagraphStyle(
            'StatValue',
            fontName='Helvetica-Bold',
            fontSize=20,
            textColor=C_BLUE,
            alignment=TA_CENTER,
            spaceAfter=2,
        ),
        'stat_label': ParagraphStyle(
            'StatLabel',
            fontName='Helvetica',
            fontSize=8,
            textColor=C_MUTED,
            alignment=TA_CENTER,
        ),
    }
    return styles


# ============================================================================
# REUSABLE COMPONENTS
# ============================================================================

def hr(styles):
    return HRFlowable(
        width='100%', thickness=1,
        color=C_BORDER, spaceAfter=8, spaceBefore=4
    )


def section_header(title: str, styles: dict):
    return KeepTogether([
        Paragraph(title, styles['section']),
        HRFlowable(width='100%', thickness=1.5, color=C_BLUE,
                   spaceAfter=6, spaceBefore=0),
    ])


def stat_card_table(stats: list, styles: dict):
    """
    Renders a row of stat cards.
    stats = [{'value': '1 234', 'label': 'Rows processed'}, ...]
    """
    col_width = (7.5 * inch) / max(len(stats), 1)

    header_row = []
    label_row  = []
    for s in stats:
        header_row.append(Paragraph(str(s['value']), styles['stat_value']))
        label_row.append(Paragraph(s['label'],        styles['stat_label']))

    t = Table(
        [header_row, label_row],
        colWidths=[col_width] * len(stats),
    )
    t.setStyle(TableStyle([
        ('BACKGROUND',  (0, 0), (-1, -1), C_LIGHT),
        ('ROWBACKGROUNDS', (0, 0), (-1, -1), [C_LIGHT, C_LIGHT]),
        ('BOX',         (0, 0), (-1, -1), 0.5, C_BORDER),
        ('INNERGRID',   (0, 0), (-1, -1), 0.5, C_BORDER),
        ('TOPPADDING',  (0, 0), (-1, -1), 10),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 10),
        ('ALIGN',       (0, 0), (-1, -1), 'CENTER'),
        ('VALIGN',      (0, 0), (-1, -1), 'MIDDLE'),
    ]))
    return t


def data_table(headers: list, rows: list, styles: dict,
               col_widths: list = None) -> Table:
    """
    Renders a standard data table with a coloured header row.
    """
    if not rows:
        return Paragraph('No data.', styles['muted'])

    header_cells = [Paragraph(h, styles['table_header']) for h in headers]
    data_rows    = []
    for row in rows:
        data_rows.append([
            Paragraph(str(cell), styles['table_cell']) for cell in row
        ])

    table_data = [header_cells] + data_rows
    n_cols     = len(headers)

    if col_widths is None:
        col_widths = [7.5 * inch / n_cols] * n_cols

    t = Table(table_data, colWidths=col_widths, repeatRows=1)
    t.setStyle(TableStyle([
        # Header
        ('BACKGROUND',    (0, 0), (-1, 0),  C_BLUE),
        ('TEXTCOLOR',     (0, 0), (-1, 0),  C_WHITE),
        # Body alternating rows
        ('ROWBACKGROUNDS', (0, 1), (-1, -1), [C_WHITE, C_LIGHT]),
        # Borders
        ('BOX',           (0, 0), (-1, -1), 0.5, C_BORDER),
        ('INNERGRID',     (0, 0), (-1, -1), 0.3, C_BORDER),
        # Padding
        ('TOPPADDING',    (0, 0), (-1, -1), 5),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 5),
        ('LEFTPADDING',   (0, 0), (-1, -1), 6),
        ('RIGHTPADDING',  (0, 0), (-1, -1), 6),
        ('VALIGN',        (0, 0), (-1, -1), 'TOP'),
    ]))
    return t


def badge(text: str, colour) -> str:
    """Inline HTML-style coloured badge for use inside Paragraph."""
    hex_col = colour.hexval() if hasattr(colour, 'hexval') else '#2563EB'
    return (
        f'<font color="{colour}">'
        f'<b>[{text.upper()}]</b>'
        f'</font>'
    )


# ============================================================================
# PAGE TEMPLATE (header / footer)
# ============================================================================

def _on_page(canvas, doc, filename: str, generated_at: str):
    canvas.saveState()
    w, h = letter

    # Top bar
    canvas.setFillColor(C_DARK)
    canvas.rect(0, h - 36, w, 36, fill=1, stroke=0)
    canvas.setFillColor(C_WHITE)
    canvas.setFont('Helvetica-Bold', 10)
    canvas.drawString(0.5 * inch, h - 22, 'Data Pipeline Report')
    canvas.setFont('Helvetica', 8)
    canvas.drawRightString(w - 0.5 * inch, h - 22, filename)

    # Footer
    canvas.setFillColor(C_MUTED)
    canvas.setFont('Helvetica', 7)
    canvas.drawString(0.5 * inch, 0.35 * inch, f'Generated {generated_at}')
    canvas.drawRightString(
        w - 0.5 * inch, 0.35 * inch,
        f'Page {doc.page}'
    )
    canvas.setStrokeColor(C_BORDER)
    canvas.line(0.5 * inch, 0.5 * inch, w - 0.5 * inch, 0.5 * inch)

    canvas.restoreState()


# ============================================================================
# SECTION BUILDERS
# ============================================================================

def build_executive_summary(report: dict, styles: dict) -> list:
    story = [section_header('Executive Summary', styles)]

    initial_rows = report.get('initial_rows', 0)
    final_rows   = report.get('final_rows',   0)
    final_cols   = report.get('final_cols',   0)
    null_rem     = report.get('null_remaining', 0)
    dedup        = report.get('dedup_after_merge', 0)

    # Count total flags raised across all validation rules
    val_rules    = report.get('validation', [])
    total_flags  = sum(
        r.get('violations', 0)
        for r in val_rules
        if r.get('action') == 'flag'
    )
    total_fixed  = sum(r.get('fixed', 0) for r in val_rules)

    stats = [
        {'value': f'{initial_rows:,}', 'label': 'Rows (input)'},
        {'value': f'{final_rows:,}',   'label': 'Rows (output)'},
        {'value': str(final_cols),     'label': 'Columns'},
        {'value': str(dedup),          'label': 'Duplicates removed'},
        {'value': str(total_flags),    'label': 'Values flagged'},
        {'value': str(null_rem),       'label': 'NULLs remaining'},
    ]
    story.append(stat_card_table(stats, styles))
    story.append(Spacer(1, 12))

    # One-line narrative
    rows_delta  = initial_rows - final_rows
    delta_txt   = (
        f'{rows_delta:,} rows were removed'
        if rows_delta > 0
        else 'No rows were removed'
    )
    narrative = (
        f'{delta_txt} during processing '
        f'({dedup:,} duplicate{"s" if dedup != 1 else ""} after merge'
        f', {total_fixed:,} value{"s" if total_fixed != 1 else ""} auto-corrected'
        f', {total_flags:,} value{"s" if total_flags != 1 else ""} flagged for human review).'
    )
    story.append(Paragraph(narrative, styles['body']))

    main_file = os.path.basename(report.get('main_file', ''))
    ref_files = [os.path.basename(r) for r in report.get('ref_files', [])]
    if main_file:
        story.append(Paragraph(
            f'<b>Main file:</b> {main_file}', styles['body']
        ))
    if ref_files:
        story.append(Paragraph(
            f'<b>Reference files:</b> {", ".join(ref_files)}', styles['body']
        ))

    return story


def build_cross_reference(report: dict, styles: dict) -> list:
    xrefs = report.get('cross_reference', [])
    if not xrefs:
        return []

    story = [section_header('Cross-Reference', styles)]
    story.append(Paragraph(
        'The following reference datasets were joined to the main file:',
        styles['body']
    ))
    story.append(Spacer(1, 6))

    headers   = ['Reference File', 'Method', 'Join Keys', 'Cols Added', 'Rows Enriched']
    col_widths = [2.0*inch, 0.9*inch, 1.8*inch, 1.0*inch, 1.2*inch]
    rows = []

    for x in xrefs:
        method = x.get('method', 'none')
        if method == 'exact':
            method_txt = 'Exact match'
        elif method == 'llm':
            method_txt = 'LLM-inferred'
        else:
            method_txt = 'Not joined'

        join_keys    = ', '.join(x.get('join_keys', [])) or '—'
        cols_added   = ', '.join(x.get('columns_added', [])) or '0'
        rows_enr     = str(x.get('rows_enriched', 0))

        rows.append([
            x.get('ref_file', ''),
            method_txt,
            join_keys,
            cols_added,
            rows_enr,
        ])

    story.append(data_table(headers, rows, styles, col_widths))

    dedup = report.get('dedup_after_merge', 0)
    if dedup > 0:
        story.append(Spacer(1, 6))
        story.append(Paragraph(
            f'<font color="#D97706"><b>⚠ {dedup:,} duplicate row'
            f'{"s" if dedup != 1 else ""} removed</b></font> after '
            f'merging all reference files.',
            styles['body']
        ))

    return story


def build_validation(report: dict, styles: dict) -> list:
    val_rules = report.get('validation', [])
    if not val_rules:
        return []

    story = [section_header('Data Validation', styles)]

    # Summary counts
    by_action = {}
    for r in val_rules:
        a = r.get('action', 'flag')
        by_action[a] = by_action.get(a, 0) + r.get('violations', 0)

    summary_parts = []
    action_colours = {'flag': C_WARN, 'null': C_MUTED, 'abs': C_BLUE,
                      'drop': C_DANGER, 'set': C_SUCCESS}
    for action, count in sorted(by_action.items()):
        col = action_colours.get(action, C_MUTED)
        summary_parts.append(
            f'<font color="{col}"><b>{count:,} {action.upper()}</b></font>'
        )
    if summary_parts:
        story.append(Paragraph(' · '.join(summary_parts), styles['body']))
        story.append(Spacer(1, 6))

    # Detailed table
    headers    = ['Column', 'Rule', 'Violations', 'Action', 'Source', 'Justification']
    col_widths = [1.1*inch, 1.6*inch, 0.75*inch, 0.65*inch, 0.7*inch, 2.4*inch]
    rows = []

    for r in val_rules:
        action = r.get('action', 'flag')
        col    = action_colours.get(action, C_MUTED)
        rows.append([
            r.get('column', ''),
            r.get('description', r.get('rule_id', '')),
            f"{r.get('violations', 0):,}",
            f'[{action.upper()}]',
            r.get('source', 'llm'),
            r.get('justification', ''),
        ])

    story.append(data_table(headers, rows, styles, col_widths))

    # Context note
    contexts = list({
        r['inferred_context']
        for r in val_rules
        if r.get('inferred_context', '').strip()
    })
    if contexts:
        story.append(Spacer(1, 8))
        story.append(Paragraph(
            f'<b>Dataset context inferred by LLM:</b> {contexts[0]}',
            styles['muted']
        ))

    return story


def build_derived_columns(report: dict, styles: dict) -> list:
    derived = report.get('derived_columns', [])
    if not derived:
        return []

    story = [section_header('Derived Column Recalculation', styles)]
    story.append(Paragraph(
        'The following columns were detected as mathematically derived '
        'from other columns. Inconsistent or missing values were recalculated.',
        styles['body']
    ))
    story.append(Spacer(1, 6))

    headers    = ['Target Column', 'Formula', 'Values Recalculated']
    col_widths = [2.2*inch, 3.3*inch, 1.8*inch]
    rows = [
        [d.get('target', ''), d.get('formula', ''), f"{d.get('recalculated', 0):,}"]
        for d in derived
    ]
    story.append(data_table(headers, rows, styles, col_widths))
    return story


def build_enrichment(report: dict, styles: dict) -> list:
    enrichment = report.get('enrichment', [])
    if not enrichment:
        return []

    story = [section_header('LLM Enrichment', styles)]
    story.append(Paragraph(
        'The LLM predicted missing values for the following columns '
        '(only predictions with confidence ≥ 0.7 were applied):',
        styles['body']
    ))
    story.append(Spacer(1, 6))

    headers    = ['Column', 'Total NULLs', 'Predicted & Applied', 'Remaining NULLs']
    col_widths = [2.2*inch, 1.5*inch, 2.0*inch, 1.7*inch]
    rows = []

    for e in enrichment:
        total     = e.get('total_null', 0)
        enriched  = e.get('enriched', 0)
        remaining = total - enriched
        rows.append([
            e.get('column', ''),
            f'{total:,}',
            f'{enriched:,}',
            f'{remaining:,}',
        ])

    story.append(data_table(headers, rows, styles, col_widths))
    return story


def build_final_state(report: dict, styles: dict) -> list:
    story = [section_header('Final Dataset State', styles)]

    rows = [
        ['Metric', 'Value'],
        ['Initial rows',          f"{report.get('initial_rows', 0):,}"],
        ['Final rows',            f"{report.get('final_rows', 0):,}"],
        ['Rows removed',          f"{report.get('initial_rows', 0) - report.get('final_rows', 0):,}"],
        ['Final columns',         str(report.get('final_cols', 0))],
        ['NULLs remaining',       str(report.get('null_remaining', 0))],
        ['Duplicates removed',    str(report.get('dedup_after_merge', 0))],
        ['Reference files used',  str(len(report.get('ref_files', [])))],
    ]

    col_widths = [3.5*inch, 3.8*inch]
    t = Table(rows[1:], colWidths=col_widths)
    t.setStyle(TableStyle([
        ('ROWBACKGROUNDS', (0, 0), (-1, -1), [C_WHITE, C_LIGHT]),
        ('BOX',           (0, 0), (-1, -1), 0.5, C_BORDER),
        ('INNERGRID',     (0, 0), (-1, -1), 0.3, C_BORDER),
        ('FONTNAME',      (0, 0), (0, -1),  'Helvetica-Bold'),
        ('FONTSIZE',      (0, 0), (-1, -1), 9),
        ('TOPPADDING',    (0, 0), (-1, -1), 5),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 5),
        ('LEFTPADDING',   (0, 0), (-1, -1), 8),
    ]))
    story.append(t)

    null_rem = report.get('null_remaining', 0)
    if null_rem > 0:
        story.append(Spacer(1, 8))
        story.append(Paragraph(
            f'<font color="#D97706"><b>{null_rem:,} NULL'
            f'{"s" if null_rem != 1 else ""} remain</b></font> — '
            f'these are identity/code columns preserved intentionally, '
            f'or values the pipeline could not safely impute.',
            styles['muted']
        ))

    return story


# ============================================================================
# MAIN BUILDER
# ============================================================================

def generate_report(report_json_path: str, output_pdf_path: str) -> str:
    """
    Build a PDF report from a pipeline _REPORT.json file.
    Returns the output path on success, raises on failure.
    """
    with open(report_json_path, 'r', encoding='utf-8') as f:
        report = json.load(f)

    generated_at = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    main_file    = os.path.basename(report.get('main_file', 'dataset'))
    styles       = build_styles()

    doc = SimpleDocTemplate(
        output_pdf_path,
        pagesize=letter,
        leftMargin=0.6 * inch,
        rightMargin=0.6 * inch,
        topMargin=0.7 * inch,
        bottomMargin=0.65 * inch,
    )

    on_page = lambda c, d: _on_page(c, d, main_file, generated_at)

    # ── Cover block ───────────────────────────────────────────────────────────
    story = [
        Spacer(1, 0.2 * inch),
        Paragraph('Data Pipeline Report', styles['title']),
        Paragraph(
            f'Generated {generated_at} · File: {main_file}',
            styles['subtitle']
        ),
        HRFlowable(width='100%', thickness=2, color=C_BLUE,
                   spaceAfter=14, spaceBefore=0),
    ]

    # ── Sections ──────────────────────────────────────────────────────────────
    for builder in [
        build_executive_summary,
        build_cross_reference,
        build_validation,
        build_derived_columns,
        build_enrichment,
        build_final_state,
    ]:
        section = builder(report, styles)
        if section:
            story.extend(section)
            story.append(Spacer(1, 8))

    doc.build(story, onFirstPage=on_page, onLaterPages=on_page)
    return output_pdf_path


# ============================================================================
# CLI
# ============================================================================

def main():
    if len(sys.argv) < 3:
        print(json.dumps({
            'status':  'error',
            'message': 'Usage: python3 report_generator.py <report.json> <output.pdf>'
        }))
        sys.exit(1)

    report_json = sys.argv[1]
    output_pdf  = sys.argv[2]

    if not os.path.exists(report_json):
        print(json.dumps({'status': 'error', 'message': f'Not found: {report_json}'}))
        sys.exit(1)

    try:
        path = generate_report(report_json, output_pdf)
        size = os.path.getsize(path)
        print(json.dumps({
            'status':     'success',
            'output_pdf': path,
            'size_bytes': size,
            'message':    f'Report generated: {os.path.basename(path)}'
        }))
    except Exception as e:
        print(json.dumps({'status': 'error', 'message': str(e)}))
        sys.exit(1)


if __name__ == '__main__':
    main()