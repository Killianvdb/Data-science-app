#!/usr/bin/env python3
"""
REPORT GENERATOR - v2.0
========================
Reads the _REPORT.json produced by cross_reference.py and generates
a detailed PDF report.

Sections:
  1. Cover page         — title, file info, quality score, TOC
  2. Executive Summary  — stat cards + narrative + rows removed breakdown
  3. Cross-Reference    — joined files, methods, columns added
  4. Validation         — rules fired, severity bar chart
  5. Derived Columns    — formulas detected and recalculated
  6. Enrichment         — per-column null breakdown with fill-rate bars
  7. Final State        — full column null table + remaining nulls
  8. Recommendations    — plain-English action items
"""

import sys, json, os, math
from datetime import datetime

from reportlab.lib.pagesizes import letter
from reportlab.lib import colors
from reportlab.lib.units import inch
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.enums import TA_LEFT, TA_CENTER, TA_RIGHT
from reportlab.platypus import (
    SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle,
    HRFlowable, PageBreak, KeepTogether
)
from reportlab.graphics.shapes import (
    Drawing, Rect, String, Line, Circle
)
from reportlab.graphics import renderPDF


# ============================================================================
# PALETTE
# ============================================================================
C_DARK    = colors.HexColor('#0F172A')
C_NAVY    = colors.HexColor('#1E3A5F')
C_BLUE    = colors.HexColor('#2563EB')
C_BLUE_LT = colors.HexColor('#DBEAFE')
C_VIOLET  = colors.HexColor('#7C3AED')
C_VIO_LT  = colors.HexColor('#EDE9FE')
C_SUCCESS = colors.HexColor('#16A34A')
C_SUC_LT  = colors.HexColor('#DCFCE7')
C_WARN    = colors.HexColor('#D97706')
C_WAR_LT  = colors.HexColor('#FEF3C7')
C_DANGER  = colors.HexColor('#DC2626')
C_DAN_LT  = colors.HexColor('#FEE2E2')
C_MUTED   = colors.HexColor('#64748B')
C_BORDER  = colors.HexColor('#E2E8F0')
C_ROW_ALT = colors.HexColor('#F8FAFC')
C_WHITE   = colors.white
C_GRAY    = colors.HexColor('#F1F5F9')


# ============================================================================
# STYLES
# ============================================================================
def build_styles():
    S = {}

    def ps(name, **kw):
        defaults = dict(fontName='Helvetica', fontSize=9,
                        textColor=C_DARK, leading=14, spaceAfter=0)
        defaults.update(kw)
        return ParagraphStyle(name, **defaults)

    S['h1']          = ps('H1', fontName='Helvetica-Bold', fontSize=22, textColor=C_DARK, leading=26, spaceAfter=2)
    S['h2']          = ps('H2', fontName='Helvetica-Bold', fontSize=13, textColor=C_BLUE, spaceBefore=16, spaceAfter=4)
    S['h3']          = ps('H3', fontName='Helvetica-Bold', fontSize=10, textColor=C_DARK, spaceBefore=10, spaceAfter=3)
    S['body']        = ps('Body', spaceAfter=4)
    S['body_bold']   = ps('BodyBold', fontName='Helvetica-Bold', spaceAfter=4)
    S['small']       = ps('Small', fontSize=8, textColor=C_MUTED, leading=11, spaceAfter=3)
    S['small_bold']  = ps('SmallBold', fontName='Helvetica-Bold', fontSize=8, textColor=C_MUTED, leading=11)
    S['th']          = ps('TH', fontName='Helvetica-Bold', fontSize=8, textColor=C_WHITE)
    S['td']          = ps('TD', fontSize=8, leading=11)
    S['td_muted']    = ps('TDMuted', fontSize=8, textColor=C_MUTED, leading=11)
    S['stat_val']    = ps('StatVal', fontName='Helvetica-Bold', fontSize=19, textColor=C_BLUE, alignment=TA_CENTER, leading=22)
    S['stat_lbl']    = ps('StatLbl', fontSize=7, textColor=C_MUTED, alignment=TA_CENTER, leading=10)
    S['toc_entry']   = ps('TOCEntry', fontSize=9, textColor=C_MUTED, leading=16)
    S['subtitle']    = ps('Subtitle', fontSize=10, textColor=C_MUTED, leading=14, spaceAfter=4)
    S['tag_flag']    = ps('TagFlag', fontName='Helvetica-Bold', fontSize=7, textColor=C_WARN)
    S['tag_abs']     = ps('TagAbs',  fontName='Helvetica-Bold', fontSize=7, textColor=C_BLUE)
    S['tag_drop']    = ps('TagDrop', fontName='Helvetica-Bold', fontSize=7, textColor=C_DANGER)
    S['rec_title']   = ps('RecTitle', fontName='Helvetica-Bold', fontSize=9, textColor=C_DARK, spaceAfter=1)
    S['rec_body']    = ps('RecBody', fontSize=8, textColor=C_MUTED, leading=12, spaceAfter=6)
    return S


# ============================================================================
# COMPONENTS
# ============================================================================

def section_header(title, S):
    return KeepTogether([
        Paragraph(title, S['h2']),
        HRFlowable(width='100%', thickness=1.5, color=C_BLUE, spaceAfter=6, spaceBefore=0),
    ])


def table_std(headers, rows, S, col_widths=None, row_colors=None):
    if not rows:
        return Paragraph('No data available.', S['small'])
    hdr  = [Paragraph(h, S['th']) for h in headers]
    body = []
    for row in rows:
        body.append([Paragraph(str(c), S['td']) for c in row])
    data = [hdr] + body
    n    = len(headers)
    if col_widths is None:
        col_widths = [7.4 * inch / n] * n
    t = Table(data, colWidths=col_widths, repeatRows=1)
    style = [
        ('BACKGROUND',    (0,0), (-1,0),  C_NAVY),
        ('TEXTCOLOR',     (0,0), (-1,0),  C_WHITE),
        ('ROWBACKGROUNDS',(0,1), (-1,-1), [C_WHITE, C_ROW_ALT]),
        ('BOX',           (0,0), (-1,-1), 0.4, C_BORDER),
        ('INNERGRID',     (0,0), (-1,-1), 0.3, C_BORDER),
        ('TOPPADDING',    (0,0), (-1,-1), 5),
        ('BOTTOMPADDING', (0,0), (-1,-1), 5),
        ('LEFTPADDING',   (0,0), (-1,-1), 7),
        ('RIGHTPADDING',  (0,0), (-1,-1), 7),
        ('VALIGN',        (0,0), (-1,-1), 'TOP'),
    ]
    if row_colors:
        for row_idx, bg in row_colors.items():
            style.append(('BACKGROUND', (0, row_idx+1), (-1, row_idx+1), bg))
    t.setStyle(TableStyle(style))
    return t


def stat_row(stats, S):
    """stats = [{'value','label','color'}]"""
    n   = len(stats)
    cw  = 7.4 * inch / n
    vals = [Paragraph(str(s['value']), S['stat_val']) for s in stats]
    lbls = [Paragraph(s['label'],      S['stat_lbl']) for s in stats]
    t = Table([vals, lbls], colWidths=[cw]*n)
    bg_style = []
    for i, s in enumerate(stats):
        bg = s.get('bg', C_BLUE_LT)
        bg_style.append(('BACKGROUND', (i,0), (i,-1), bg))
        val_color = s.get('color', C_BLUE)
        # Can't set per-cell font color via TableStyle easily for Paragraph,
        # so color is baked into the Paragraph style above
    t.setStyle(TableStyle([
        ('ROWBACKGROUNDS', (0,0), (-1,-1), [C_BLUE_LT, C_BLUE_LT]),
        ('BOX',           (0,0), (-1,-1), 0.4, C_BORDER),
        ('INNERGRID',     (0,0), (-1,-1), 0.4, C_BORDER),
        ('TOPPADDING',    (0,0), (-1,-1), 10),
        ('BOTTOMPADDING', (0,0), (-1,-1), 10),
        ('ALIGN',         (0,0), (-1,-1), 'CENTER'),
        ('VALIGN',        (0,0), (-1,-1), 'MIDDLE'),
    ]))
    return t


# ============================================================================
# QUALITY SCORE
# ============================================================================
def compute_quality_score(report):
    """
    0-100 score from four components:
      - Completeness   : 1 - (null_remaining / max(total_cells,1))   × 30
      - Dedup rate     : 1 - (dedup / max(initial_rows,1))           × 25
      - Validation pass: 1 - (total_flags / max(initial_rows,1))     × 25
      - Enrichment     : enriched / max(total_nulls_before,1)        × 20
    """
    initial = max(report.get('initial_rows', 1), 1)
    final   = report.get('final_rows', initial)
    cols    = max(report.get('final_cols', 1), 1)
    null_rem = report.get('null_remaining', 0)
    dedup    = report.get('dedup_after_merge', 0)

    total_cells = final * cols
    completeness = 1.0 - (null_rem / max(total_cells, 1))

    dedup_score = 1.0 - min(dedup / initial, 1.0)

    val_rules   = report.get('validation', [])
    total_flags = sum(r.get('violations', 0) for r in val_rules if r.get('action') == 'flag')
    val_score   = 1.0 - min(total_flags / initial, 1.0)

    enrichment  = report.get('enrichment', [])
    total_nulls_before = sum(e.get('total_null', 0) for e in enrichment)
    total_enriched     = sum(e.get('enriched',   0) for e in enrichment)
    enr_score = (total_enriched / total_nulls_before) if total_nulls_before > 0 else 1.0

    score = (
        completeness * 30 +
        dedup_score  * 25 +
        val_score    * 25 +
        enr_score    * 20
    )
    return round(min(max(score, 0), 100))


def quality_score_drawing(score):
    """Returns a Drawing with a horizontal score bar."""
    W, H = 7.4 * inch, 52
    d    = Drawing(W, H)

    # Background track
    d.add(Rect(0, 28, W, 14, rx=7, ry=7,
               fillColor=C_GRAY, strokeColor=None))

    # Filled portion
    if score > 0:
        fill_w = (score / 100.0) * W
        if score >= 80:
            bar_color = C_SUCCESS
        elif score >= 55:
            bar_color = C_WARN
        else:
            bar_color = C_DANGER
        d.add(Rect(0, 28, fill_w, 14, rx=7, ry=7,
                   fillColor=bar_color, strokeColor=None))

    # Score text
    if score >= 80:
        grade, gc = 'Good', C_SUCCESS
    elif score >= 55:
        grade, gc = 'Fair', C_WARN
    else:
        grade, gc = 'Poor', C_DANGER

    d.add(String(0,  10, f'Data Quality Score:  {score}/100  —  {grade}',
                 fontName='Helvetica-Bold', fontSize=9, fillColor=C_DARK))
    d.add(String(W,  10, '0=Poor · 55=Fair · 80=Good',
                 fontName='Helvetica', fontSize=7, fillColor=C_MUTED,
                 textAnchor='end'))
    return d


# ============================================================================
# ROW REMOVAL BREAKDOWN
# ============================================================================
def rows_removed_breakdown(report):
    """Return dict with keys: duplicates, validation_drops, total."""
    dedup  = report.get('dedup_after_merge', 0)
    val_rules = report.get('validation', [])
    drops  = sum(r.get('violations', 0) for r in val_rules if r.get('action') == 'drop')
    initial = report.get('initial_rows', 0)
    final   = report.get('final_rows',   0)
    other   = max((initial - final) - dedup - drops, 0)
    return {
        'duplicates':        dedup,
        'validation_drops':  drops,
        'other':             other,
        'total':             initial - final,
    }


# ============================================================================
# SEVERITY BAR CHART
# ============================================================================
def severity_bar_chart(val_rules):
    """Horizontal bar chart of violation counts by action type."""
    action_map = {}
    for r in val_rules:
        a = r.get('action', 'flag')
        action_map[a] = action_map.get(a, 0) + r.get('violations', 0)

    if not action_map:
        return None

    action_colors = {
        'flag': C_WARN, 'abs': C_BLUE, 'drop': C_DANGER,
        'null': C_MUTED, 'set': C_SUCCESS,
    }
    labels  = list(action_map.keys())
    values  = [action_map[l] for l in labels]
    max_val = max(values) if values else 1

    BAR_H   = 16
    GAP     = 6
    LABEL_W = 60
    CHART_W = 5.5 * inch
    H       = len(labels) * (BAR_H + GAP) + 20
    W       = 7.4 * inch

    d = Drawing(W, H)
    y = H - BAR_H - 10

    for label, val in zip(labels, values):
        bar_w = max((val / max_val) * CHART_W, 4)
        bc    = action_colors.get(label, C_MUTED)

        d.add(String(LABEL_W - 5, y + 4,
                     label.upper(),
                     fontName='Helvetica-Bold', fontSize=7,
                     fillColor=C_DARK, textAnchor='end'))

        d.add(Rect(LABEL_W, y, CHART_W, BAR_H, rx=3, ry=3,
                   fillColor=C_GRAY, strokeColor=None))
        d.add(Rect(LABEL_W, y, bar_w, BAR_H, rx=3, ry=3,
                   fillColor=bc, strokeColor=None))
        d.add(String(LABEL_W + bar_w + 6, y + 4,
                     f'{val:,}',
                     fontName='Helvetica-Bold', fontSize=7,
                     fillColor=C_DARK))
        y -= (BAR_H + GAP)

    return d


# ============================================================================
# FILL-RATE BARS  (enrichment)
# ============================================================================
def fill_rate_bars(enrichment):
    """Horizontal bar per column showing filled vs remaining."""
    if not enrichment:
        return None

    BAR_H   = 14
    GAP     = 5
    LABEL_W = 110
    CHART_W = 5.0 * inch
    H       = len(enrichment) * (BAR_H + GAP) + 20
    W       = 7.4 * inch

    d = Drawing(W, H)
    y = H - BAR_H - 10

    for e in enrichment:
        col     = e.get('column', '')
        total   = e.get('total_null', 0)
        filled  = e.get('enriched', 0)
        pct     = (filled / total * 100) if total > 0 else 0
        fill_w  = (pct / 100) * CHART_W

        # Column name
        display = col if len(col) <= 18 else col[:16] + '…'
        d.add(String(LABEL_W - 5, y + 3,
                     display,
                     fontName='Helvetica', fontSize=7,
                     fillColor=C_DARK, textAnchor='end'))

        # Background track
        d.add(Rect(LABEL_W, y, CHART_W, BAR_H, rx=3, ry=3,
                   fillColor=C_GRAY, strokeColor=None))

        # Filled portion
        if fill_w > 0:
            d.add(Rect(LABEL_W, y, fill_w, BAR_H, rx=3, ry=3,
                       fillColor=C_SUCCESS, strokeColor=None))

        # Percentage label
        d.add(String(LABEL_W + CHART_W + 6, y + 3,
                     f'{pct:.0f}% filled  ({filled}/{total})',
                     fontName='Helvetica', fontSize=7,
                     fillColor=C_MUTED))
        y -= (BAR_H + GAP)

    return d


# ============================================================================
# PAGE TEMPLATE
# ============================================================================
def _on_page(canvas, doc, filename, generated_at):
    canvas.saveState()
    W, H = letter

    # Top bar
    canvas.setFillColor(C_NAVY)
    canvas.rect(0, H - 30, W, 30, fill=1, stroke=0)
    canvas.setFillColor(C_WHITE)
    canvas.setFont('Helvetica-Bold', 9)
    canvas.drawString(0.5*inch, H - 19, 'CleanMyData  ·  Pipeline Report')
    canvas.setFont('Helvetica', 8)
    canvas.drawRightString(W - 0.5*inch, H - 19, filename)

    # Footer
    canvas.setFillColor(C_MUTED)
    canvas.setFont('Helvetica', 7)
    canvas.drawString(0.5*inch, 0.32*inch, f'Generated {generated_at}')
    canvas.drawRightString(W - 0.5*inch, 0.32*inch, f'Page {doc.page}')
    canvas.setStrokeColor(C_BORDER)
    canvas.line(0.5*inch, 0.45*inch, W - 0.5*inch, 0.45*inch)

    canvas.restoreState()


# ============================================================================
# SECTION BUILDERS
# ============================================================================

def build_cover(report, S, generated_at, main_file, score):
    story = [Spacer(1, 0.15*inch)]

    # Title block
    story.append(Paragraph('Pipeline Report', S['h1']))
    story.append(Paragraph(
        f'Generated {generated_at}',
        S['subtitle']
    ))
    story.append(HRFlowable(width='100%', thickness=2, color=C_BLUE,
                             spaceAfter=10, spaceBefore=4))

    # File info table
    ref_files = [os.path.basename(r) for r in report.get('ref_files', [])]
    info_rows = [
        ['Main file',       os.path.basename(report.get('main_file', main_file))],
        ['Reference files', ', '.join(ref_files) if ref_files else '—'],
        ['Pipeline mode',   report.get('pipeline_mode', 'full_pipeline').replace('_', ' ').title()],
        ['Dataset context', (report.get('context') or '—')[:80]],
    ]
    t = Table(info_rows, colWidths=[1.4*inch, 5.9*inch])
    t.setStyle(TableStyle([
        ('FONTNAME',      (0,0), (0,-1), 'Helvetica-Bold'),
        ('FONTSIZE',      (0,0), (-1,-1), 8),
        ('TEXTCOLOR',     (0,0), (0,-1), C_MUTED),
        ('TEXTCOLOR',     (1,0), (1,-1), C_DARK),
        ('TOPPADDING',    (0,0), (-1,-1), 4),
        ('BOTTOMPADDING', (0,0), (-1,-1), 4),
        ('ROWBACKGROUNDS',(0,0), (-1,-1), [C_WHITE, C_ROW_ALT]),
        ('BOX',           (0,0), (-1,-1), 0.4, C_BORDER),
        ('INNERGRID',     (0,0), (-1,-1), 0.3, C_BORDER),
        ('LEFTPADDING',   (0,0), (-1,-1), 8),
    ]))
    story.append(t)
    story.append(Spacer(1, 12))

    # Quality score bar
    story.append(quality_score_drawing(score))
    story.append(Spacer(1, 16))

    # Table of contents
    story.append(HRFlowable(width='100%', thickness=0.5, color=C_BORDER, spaceAfter=6))
    story.append(Paragraph('Contents', S['small_bold']))
    toc_items = [
        '1.  Executive Summary',
        '2.  Cross-Reference',
        '3.  Data Validation',
        '4.  Derived Column Recalculation',
        '5.  LLM Enrichment',
        '6.  Final Dataset State',
        '7.  Recommendations',
    ]
    for item in toc_items:
        story.append(Paragraph(item, S['toc_entry']))
    story.append(Spacer(1, 6))
    story.append(HRFlowable(width='100%', thickness=0.5, color=C_BORDER, spaceAfter=0))

    return story


def build_executive_summary(report, S, score):
    story = [section_header('1.  Executive Summary', S)]

    initial = report.get('initial_rows', 0)
    final   = report.get('final_rows',   0)
    cols    = report.get('final_cols',   0)
    null_rem = report.get('null_remaining', 0)
    dedup   = report.get('dedup_after_merge', 0)
    val_rules = report.get('validation', [])
    total_flags = sum(r.get('violations', 0) for r in val_rules if r.get('action') == 'flag')
    total_fixed = sum(r.get('fixed', 0) for r in val_rules)

    stats = [
        {'value': f'{initial:,}',    'label': 'Input rows'},
        {'value': f'{final:,}',      'label': 'Output rows'},
        {'value': str(cols),         'label': 'Columns'},
        {'value': str(dedup),        'label': 'Duplicates removed'},
        {'value': str(total_flags),  'label': 'Values flagged'},
        {'value': str(null_rem),     'label': 'NULLs remaining'},
    ]
    story.append(stat_row(stats, S))
    story.append(Spacer(1, 12))

    # Narrative
    delta = initial - final
    narrative = (
        f'The pipeline processed <b>{initial:,} rows</b> and produced '
        f'<b>{final:,} clean rows</b> ({delta:,} removed). '
        f'<b>{dedup:,} duplicate{"s" if dedup != 1 else ""}</b> were identified after merging reference files. '
        f'<b>{total_fixed:,} value{"s" if total_fixed != 1 else ""}</b> were automatically corrected and '
        f'<b>{total_flags:,}</b> flagged for human review. '
        f'<b>{null_rem}</b> null{"s" if null_rem != 1 else ""} remain in the output.'
    )
    story.append(Paragraph(narrative, S['body']))
    story.append(Spacer(1, 10))

    # Rows removed breakdown
    rb = rows_removed_breakdown(report)
    story.append(Paragraph('Rows removed — breakdown', S['h3']))
    breakdown_rows = [
        ['Reason',               'Count', 'Share of removed rows'],
        ['Duplicates after merge', f"{rb['duplicates']:,}",
         f"{rb['duplicates']/max(rb['total'],1)*100:.1f}%" if rb['total'] else '—'],
        ['Dropped by validation rule', f"{rb['validation_drops']:,}",
         f"{rb['validation_drops']/max(rb['total'],1)*100:.1f}%" if rb['total'] else '—'],
        ['Other (parse/format errors)', f"{rb['other']:,}",
         f"{rb['other']/max(rb['total'],1)*100:.1f}%" if rb['total'] else '—'],
        ['Total removed',         f"{rb['total']:,}", '100%'],
    ]
    cw = [3.2*inch, 1.5*inch, 2.6*inch]
    t  = table_std(breakdown_rows[0], breakdown_rows[1:], S, col_widths=cw,
                   row_colors={len(breakdown_rows)-2: C_BLUE_LT})
    story.append(t)

    return story


def build_cross_reference(report, S):
    xrefs = report.get('cross_reference', [])
    if not xrefs:
        return []

    story = [section_header('2.  Cross-Reference', S)]
    story.append(Paragraph(
        'The following reference datasets were joined to the main file during the merge step.',
        S['body']
    ))
    story.append(Spacer(1, 6))

    headers = ['Reference File', 'Join Method', 'Join Keys', 'Columns Added', 'Rows Enriched']
    cw      = [1.9*inch, 1.0*inch, 1.4*inch, 1.7*inch, 1.3*inch]
    rows    = []

    for x in xrefs:
        method = {'exact': 'Exact match', 'llm': 'LLM-inferred'}.get(x.get('method',''), 'Not joined')
        rows.append([
            os.path.basename(x.get('ref_file', '')),
            method,
            ', '.join(x.get('join_keys', [])) or '—',
            ', '.join(x.get('columns_added', [])) or '0',
            str(x.get('rows_enriched', 0)),
        ])

    story.append(table_std(headers, rows, S, col_widths=cw))

    dedup = report.get('dedup_after_merge', 0)
    if dedup > 0:
        story.append(Spacer(1, 6))
        story.append(Paragraph(
            f'<font color="#D97706"><b>⚠  {dedup:,} duplicate row{"s" if dedup!=1 else ""} '
            f'removed</b></font> after merging all reference files into the main dataset.',
            S['body']
        ))
    return story


def build_validation(report, S):
    val_rules = report.get('validation', [])
    if not val_rules:
        return []

    story = [section_header('3.  Data Validation', S)]

    # Severity bar chart
    chart = severity_bar_chart(val_rules)
    if chart:
        story.append(Paragraph('Violations by action type', S['h3']))
        story.append(chart)
        story.append(Spacer(1, 10))

    # Detailed table
    story.append(Paragraph('Rule-level detail', S['h3']))
    headers = ['Column', 'Description', 'Violations', 'Action', 'Source', 'Justification']
    cw      = [0.9*inch, 1.6*inch, 0.7*inch, 0.6*inch, 0.6*inch, 2.9*inch]
    action_colors = {'flag': C_WAR_LT, 'drop': C_DAN_LT, 'abs': C_BLUE_LT, 'set': C_SUC_LT}
    rows       = []
    row_colors = {}

    for i, r in enumerate(val_rules):
        action = r.get('action', 'flag')
        if action in action_colors:
            row_colors[i] = action_colors[action]
        rows.append([
            r.get('column', ''),
            r.get('description', r.get('rule_id', '')),
            f"{r.get('violations', 0):,}",
            action.upper(),
            r.get('source', 'llm'),
            r.get('justification', ''),
        ])

    story.append(table_std(headers, rows, S, col_widths=cw, row_colors=row_colors))

    contexts = list({r['inferred_context'] for r in val_rules if r.get('inferred_context','').strip()})
    if contexts:
        story.append(Spacer(1, 6))
        story.append(Paragraph(f'<b>Dataset context inferred by LLM:</b> {contexts[0]}', S['small']))

    return story


def build_derived_columns(report, S):
    derived = report.get('derived_columns', [])
    if not derived:
        return []

    story = [section_header('4.  Derived Column Recalculation', S)]
    story.append(Paragraph(
        'These columns were detected as mathematically derived from other columns. '
        'Inconsistent or missing values were recalculated using the detected formula.',
        S['body']
    ))
    story.append(Spacer(1, 6))
    headers = ['Target Column', 'Detected Formula', 'Values Recalculated']
    cw      = [2.0*inch, 3.2*inch, 2.1*inch]
    rows    = [[d.get('target',''), d.get('formula',''), f"{d.get('recalculated',0):,}"] for d in derived]
    story.append(table_std(headers, rows, S, col_widths=cw))
    return story


def build_enrichment(report, S):
    enrichment = report.get('enrichment', [])
    if not enrichment:
        return []

    story = [section_header('5.  LLM Enrichment', S)]
    story.append(Paragraph(
        'The AI predicted missing values for the columns below. '
        'Only predictions with confidence ≥ 0.7 were applied. '
        'The bar chart shows the fill rate achieved per column.',
        S['body']
    ))
    story.append(Spacer(1, 8))

    # Fill-rate bar chart
    bars = fill_rate_bars(enrichment)
    if bars:
        story.append(bars)
        story.append(Spacer(1, 10))

    # Table
    headers = ['Column', 'NULLs before', 'Filled by AI', 'Fill rate', 'NULLs remaining']
    cw      = [1.7*inch, 1.2*inch, 1.2*inch, 1.2*inch, 1.9*inch]
    rows    = []
    for e in enrichment:
        total    = e.get('total_null', 0)
        filled   = e.get('enriched', 0)
        remain   = total - filled
        pct      = f'{filled/total*100:.1f}%' if total > 0 else '—'
        rows.append([e.get('column',''), f'{total:,}', f'{filled:,}', pct, f'{remain:,}'])
    story.append(table_std(headers, rows, S, col_widths=cw))
    return story


def build_final_state(report, S):
    story = [section_header('6.  Final Dataset State', S)]

    initial  = report.get('initial_rows', 0)
    final    = report.get('final_rows',   0)
    cols     = report.get('final_cols',   0)
    null_rem = report.get('null_remaining', 0)
    dedup    = report.get('dedup_after_merge', 0)
    ref_files = report.get('ref_files', [])

    # Summary table
    summary = [
        ['Initial rows',         f'{initial:,}'],
        ['Final rows',           f'{final:,}'],
        ['Rows removed',         f'{initial-final:,}'],
        ['Final columns',        str(cols)],
        ['Duplicates removed',   str(dedup)],
        ['Reference files used', str(len(ref_files))],
        ['NULLs remaining',      str(null_rem)],
    ]
    t = Table(summary, colWidths=[3.0*inch, 4.3*inch])
    t.setStyle(TableStyle([
        ('FONTNAME',      (0,0), (0,-1), 'Helvetica-Bold'),
        ('FONTSIZE',      (0,0), (-1,-1), 9),
        ('TEXTCOLOR',     (0,0), (0,-1), C_MUTED),
        ('ROWBACKGROUNDS',(0,0), (-1,-1), [C_WHITE, C_ROW_ALT]),
        ('BOX',           (0,0), (-1,-1), 0.4, C_BORDER),
        ('INNERGRID',     (0,0), (-1,-1), 0.3, C_BORDER),
        ('TOPPADDING',    (0,0), (-1,-1), 5),
        ('BOTTOMPADDING', (0,0), (-1,-1), 5),
        ('LEFTPADDING',   (0,0), (-1,-1), 8),
        ('BACKGROUND',    (0, len(summary)-1), (-1, len(summary)-1),
         C_DAN_LT if null_rem > 0 else C_SUC_LT),
    ]))
    story.append(t)
    story.append(Spacer(1, 12))

    # Per-column null breakdown
    col_nulls = report.get('column_nulls', [])
    if col_nulls:
        story.append(Paragraph('Per-column null detail', S['h3']))
        headers = ['Column', 'Type', 'NULLs before', 'NULLs after', 'Δ Filled', 'Status']
        cw      = [1.6*inch, 0.9*inch, 1.0*inch, 1.0*inch, 0.9*inch, 1.9*inch]
        rows    = []
        row_colors = {}
        for i, c in enumerate(col_nulls):
            before = c.get('nulls_before', 0)
            after  = c.get('nulls_after',  0)
            delta  = before - after
            if after == 0 and before > 0:
                status = 'Fully resolved'
                row_colors[i] = C_SUC_LT
            elif after > 0 and before > 0:
                status = 'Partially filled'
                row_colors[i] = C_WAR_LT
            elif after > 0 and before == 0:
                status = 'Introduced (merge)'
                row_colors[i] = C_DAN_LT
            else:
                status = 'No nulls'
            rows.append([
                c.get('column',''), c.get('type',''),
                str(before), str(after), str(delta), status
            ])
        story.append(table_std(headers, rows, S, col_widths=cw, row_colors=row_colors))

    if null_rem > 0:
        story.append(Spacer(1, 8))
        story.append(Paragraph(
            f'<font color="#D97706"><b>{null_rem:,} NULL{"s" if null_rem!=1 else ""} remain</b></font> '
            f'in the output. These are identity/code columns preserved intentionally, '
            f'or values the AI could not impute with sufficient confidence (threshold: 0.7).',
            S['small']
        ))

    return story


# ============================================================================
# RECOMMENDATIONS
# ============================================================================
def build_recommendations(report, S, score):
    story = [section_header('7.  Recommendations', S)]
    story.append(Paragraph(
        'Based on what the pipeline found, here are suggested follow-up actions:',
        S['body']
    ))
    story.append(Spacer(1, 8))

    recs = []

    # 1. Quality score
    if score < 55:
        recs.append((
            '🔴  Low data quality score',
            f'The dataset scored {score}/100. Consider reviewing the source system — '
            'high duplicate rates or many flagged values suggest a data entry or export issue.'
        ))
    elif score < 80:
        recs.append((
            '🟡  Moderate data quality',
            f'The dataset scored {score}/100. Review the flagged values below and consider '
            'adding range rules or no-negative constraints to tighten future runs.'
        ))
    else:
        recs.append((
            '🟢  Good data quality',
            f'The dataset scored {score}/100. The pipeline made only minor corrections. '
            'Continue monitoring for drift in future uploads.'
        ))

    # 2. Remaining nulls
    null_rem  = report.get('null_remaining', 0)
    col_nulls = report.get('column_nulls', [])
    null_cols = [c['column'] for c in col_nulls if c.get('nulls_after', 0) > 0]
    if null_rem > 0 and null_cols:
        recs.append((
            f'⚠  {null_rem:,} NULL{"s" if null_rem!=1 else ""} remain',
            f'Columns still containing nulls: {", ".join(null_cols[:5])}{"..." if len(null_cols)>5 else ""}. '
            'Check whether these are optional fields or indicate a data source issue.'
        ))

    # 3. Flagged values
    val_rules   = report.get('validation', [])
    flag_rules  = [r for r in val_rules if r.get('action') == 'flag']
    total_flags = sum(r.get('violations', 0) for r in flag_rules)
    if total_flags > 0:
        flag_cols = list({r['column'] for r in flag_rules})
        recs.append((
            f'🔍  {total_flags:,} value{"s" if total_flags!=1 else ""} flagged for review',
            f'Flagged columns: {", ".join(flag_cols[:5])}{"..." if len(flag_cols)>5 else ""}. '
            'Review the _FLAG columns in the output CSV and validate with the data owner.'
        ))

    # 4. Duplicates
    dedup = report.get('dedup_after_merge', 0)
    initial = report.get('initial_rows', 1)
    if dedup / initial > 0.05:
        recs.append((
            f'📋  High duplicate rate ({dedup/initial*100:.1f}%)',
            f'{dedup:,} duplicates were found after merging — this is above 5%. '
            'Investigate whether the source data or the merge logic is producing redundant rows.'
        ))

    # 5. Enrichment low
    enrichment = report.get('enrichment', [])
    low_fill = [e['column'] for e in enrichment
                if e.get('total_null',0) > 0 and
                e.get('enriched',0)/e.get('total_null',1) < 0.3]
    if low_fill:
        recs.append((
            '🤖  Low AI fill rate on some columns',
            f'The AI filled less than 30% of nulls in: {", ".join(low_fill[:4])}. '
            'These columns may need manual attention or a lookup table for better coverage.'
        ))

    # 6. Future dates
    future_rules = [r for r in val_rules if 'future' in r.get('description','').lower()]
    if future_rules:
        recs.append((
            '📅  Future dates detected',
            'Date columns contain values in the future. Verify whether this is expected '
            '(e.g. scheduled orders) or indicates a data entry error.'
        ))

    if not recs:
        recs.append((
            '✅  No critical issues found',
            'The pipeline completed without raising any significant concerns. '
            'The dataset appears clean and ready for analysis.'
        ))

    # Render recommendations as cards
    for title, body in recs:
        block = Table(
            [[Paragraph(title, S['rec_title'])],
             [Paragraph(body,  S['rec_body'])]],
            colWidths=[7.4*inch]
        )
        block.setStyle(TableStyle([
            ('BACKGROUND',    (0,0), (-1,-1), C_ROW_ALT),
            ('BOX',           (0,0), (-1,-1), 0.5, C_BORDER),
            ('LEFTPADDING',   (0,0), (-1,-1), 10),
            ('RIGHTPADDING',  (0,0), (-1,-1), 10),
            ('TOPPADDING',    (0,0), (-1,-1), 7),
            ('BOTTOMPADDING', (0,0), (-1,-1), 7),
        ]))
        story.append(KeepTogether([block, Spacer(1, 6)]))

    return story


# ============================================================================
# MAIN
# ============================================================================
def generate_report(report_json_path, output_pdf_path):
    with open(report_json_path, 'r', encoding='utf-8') as f:
        report = json.load(f)

    generated_at = datetime.now().strftime('%Y-%m-%d  %H:%M:%S')
    main_file    = os.path.basename(report.get('main_file', 'dataset'))
    S            = build_styles()
    score        = compute_quality_score(report)

    doc = SimpleDocTemplate(
        output_pdf_path,
        pagesize=letter,
        leftMargin=0.55*inch, rightMargin=0.55*inch,
        topMargin=0.65*inch,  bottomMargin=0.60*inch,
    )

    on_page = lambda c, d: _on_page(c, d, main_file, generated_at)

    story = []
    for builder, args in [
        (build_cover,              (report, S, generated_at, main_file, score)),
        (build_executive_summary,  (report, S, score)),
        (build_cross_reference,    (report, S)),
        (build_validation,         (report, S)),
        (build_derived_columns,    (report, S)),
        (build_enrichment,         (report, S)),
        (build_final_state,        (report, S)),
        (build_recommendations,    (report, S, score)),
    ]:
        section = builder(*args)
        if section:
            story.extend(section)
            story.append(Spacer(1, 10))

    doc.build(story, onFirstPage=on_page, onLaterPages=on_page)
    return output_pdf_path


def main():
    if len(sys.argv) < 3:
        print(json.dumps({'status': 'error', 'message': 'Usage: report_generator.py <report.json> <output.pdf>'}))
        sys.exit(1)

    rj, op = sys.argv[1], sys.argv[2]
    if not os.path.exists(rj):
        print(json.dumps({'status': 'error', 'message': f'Not found: {rj}'}))
        sys.exit(1)

    try:
        path = generate_report(rj, op)
        print(json.dumps({'status': 'success', 'output_pdf': path,
                          'size_bytes': os.path.getsize(path)}))
    except Exception as e:
        import traceback
        print(json.dumps({'status': 'error', 'message': str(e), 'trace': traceback.format_exc()}))
        sys.exit(1)

if __name__ == '__main__':
    main()