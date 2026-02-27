<x-app-layout>

<style>
@import url('https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap');

.up * { font-family: 'Sora', sans-serif; box-sizing: border-box; }
.up .mono { font-family: 'JetBrains Mono', monospace; }

/* ── Layout ── */
.up { max-width: 860px; margin: 0 auto; padding-bottom: 48px; }

/* ── Card ── */
.card {
    background: #ffffff;
    border: 1.5px solid #f0f0f0;
    border-radius: 20px;
    padding: 28px;
    margin-bottom: 12px;
}

/* ── Section label ── */
.sec-label {
    font-size: 12px; font-weight: 600; letter-spacing: .07em;
    text-transform: uppercase; color: #a0a0a0; margin: 0 0 14px;
}

/* ── Drop zone ── */
.drop-zone {
    position: relative;
    border: 2px dashed #e4e4e4;
    border-radius: 14px;
    height: 128px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    transition: border-color .18s, background .18s;
    background: #fafafa;
    overflow: hidden;
}
.drop-zone input[type=file] {
    position: absolute; inset: 0; opacity: 0; cursor: pointer; z-index: 10; width: 100%; height: 100%;
}
.drop-zone:hover   { border-color: #bfdbfe; background: #f0f9ff; }
.drop-zone.dragover { border-color: #3b82f6; background: #eff6ff; }
.drop-zone.has-file { border-style: solid; border-color: #3b82f6; background: #eff6ff; }

/* ── Reference file chips ── */
.ref-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; min-height: 0; }
.ref-chip {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 10px 4px 8px; border-radius: 20px;
    background: #eff6ff; border: 1px solid #bfdbfe;
    font-size: 13px; color: #1e40af; font-family: 'JetBrains Mono', monospace;
}
.ref-chip button {
    background: none; border: none; cursor: pointer; padding: 0;
    color: #93c5fd; line-height: 1; font-size: 14px; display: flex;
}
.ref-chip button:hover { color: #2563eb; }
.ref-add-btn {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 6px 12px; border-radius: 8px;
    background: #eff6ff; border: 1.5px dashed #bfdbfe;
    font-size: 13px; color: #2563eb; font-weight: 500;
    cursor: pointer; transition: background .15s, border-color .15s;
    position: relative; overflow: hidden;
}
.ref-add-btn input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
.ref-add-btn:hover { background: #dbeafe; border-color: #3b82f6; }

/* ── Mode buttons ── */
.mode-btn {
    border: 2px solid #e8edf2; border-radius: 14px; padding: 20px;
    cursor: pointer; transition: border-color .18s, background .18s;
    background: #fafafa;
}
.mode-btn:hover { border-color: #bfdbfe; background: #f0f9ff; }
.mode-btn.selected { border-color: #2563eb; background: #eff6ff; }
.mode-btn-icon {
    width: 40px; height: 40px; border-radius: 11px;
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 10px; background: #dbeafe; transition: background .18s;
}
.mode-btn.selected .mode-btn-icon { background: #2563eb; }
.mode-btn.selected .mode-btn-icon svg { stroke: #fff; }
.mode-btn-title { font-size: 14px; font-weight: 600; color: #1a1a1a; margin-bottom: 3px; }
.mode-btn-desc  { font-size: 12px; color: #888; line-height: 1.5; }
.mode-btn.selected .mode-btn-title { color: #1e40af; }

/* ── Text inputs ── */
.u-input {
    width: 100%; padding: 9px 12px;
    border: 1.5px solid #efefef; border-radius: 10px;
    font-size: 14px; color: #1a1a1a; background: #fafafa;
    outline: none; transition: border-color .15s;
    font-family: 'Sora', sans-serif;
}
.u-input:focus { border-color: #3b82f6; background: #fff; }
.u-input::placeholder { color: #bbb; }

.u-textarea {
    width: 100%; padding: 10px 12px;
    border: 1.5px solid #efefef; border-radius: 10px;
    font-size: 14px; color: #1a1a1a; background: #fafafa;
    outline: none; resize: none; line-height: 1.6;
    transition: border-color .15s;
    font-family: 'Sora', sans-serif;
}
.u-textarea:focus { border-color: #3b82f6; background: #fff; }
.u-textarea::placeholder { color: #bbb; }

/* ── Context toggle ── */
.ctx-toggle {
    width: 100%; display: flex; align-items: center; justify-content: space-between;
    background: none; border: none; padding: 0; cursor: pointer; text-align: left;
}
.ctx-arrow {
    width: 26px; height: 26px; border-radius: 8px; background: #f5f5f5;
    display: flex; align-items: center; justify-content: center;
    transition: background .15s, transform .22s; flex-shrink: 0;
}
.ctx-arrow.open { background: #dbeafe; transform: rotate(180deg); }

/* ── Toggle switch ── */
.sw-row { display: flex; align-items: flex-start; gap: 12px; padding: 13px 0; }
.sw-row + .sw-row { border-top: 1px solid #f5f5f5; }
.sw { position: relative; width: 38px; height: 22px; flex-shrink: 0; margin-top: 1px; }
.sw input { opacity: 0; width: 0; height: 0; }
.sw-track {
    position: absolute; inset: 0; border-radius: 22px;
    background: #e5e7eb; cursor: pointer; transition: background .2s;
}
.sw-track::after {
    content: ''; position: absolute;
    width: 16px; height: 16px; border-radius: 50%;
    background: #fff; top: 3px; left: 3px;
    transition: transform .2s; box-shadow: 0 1px 4px rgba(0,0,0,.15);
}
.sw input:checked + .sw-track { background: #3b82f6; }
.sw input:checked + .sw-track::after { transform: translateX(16px); }

/* ── Add row buttons ── */
.add-row-btn {
    font-size: 12px; color: #3b82f6; font-weight: 500;
    background: none; border: none; cursor: pointer; padding: 0; white-space: nowrap;
    flex-shrink: 0;
}
.add-row-btn:hover { color: #1d4ed8; }
.rm-btn {
    font-size: 16px; color: #d1d5db; background: none; border: none;
    cursor: pointer; padding: 0; line-height: 1; flex-shrink: 0;
    transition: color .12s;
}
.rm-btn:hover { color: #ef4444; }

/* ── Divider ── */
.divider { height: 1px; background: #f5f5f5; margin: 18px 0; }

/* ── Submit ── */
.submit-row { display: flex; align-items: center; flex-direction: column; }
.btn-submit {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 13px 32px; border-radius: 13px;
    background: #2563eb; color: #fff;
    font-size: 15px; font-weight: 600; font-family: 'Sora', sans-serif;
    border: none; cursor: pointer;
    box-shadow: 0 4px 20px rgba(37,99,235,.3);
    transition: background .15s, transform .15s, box-shadow .15s;
    letter-spacing: .01em;
}
.btn-submit:hover { background: #1d4ed8; transform: translateY(-1px); box-shadow: 0 6px 24px rgba(37,99,235,.38); }
.btn-submit:active { transform: translateY(0); }
.btn-submit:disabled { opacity: .5; cursor: not-allowed; transform: none; box-shadow: none; }

/* ── Stat chips ── */
.chips-row { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
.s-chip { flex: 1; min-width: 100px; padding: 12px 14px; border-radius: 12px; border: 1.5px solid #f0f0f0; }
.s-chip .v { font-size: 22px; font-weight: 700; color: #1a1a1a; line-height: 1; }
.s-chip .l { font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: #aaa; margin-top: 4px; }
.s-chip.blue  { background: #f0f5ff; border-color: #c7d7fe; }
.s-chip.blue .v { color: #3730a3; }
.s-chip.green { background: #f0fdf4; border-color: #bbf7d0; }
.s-chip.green .v { color: #15803d; }
.s-chip.amber { background: #fffbeb; border-color: #fde68a; }
.s-chip.amber .v { color: #b45309; }

/* ── Download buttons ── */
.dl-row { display: flex; flex-wrap: wrap; gap: 8px; }
.dl-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 9px 16px; border-radius: 9px;
    font-size: 13px; font-weight: 500; text-decoration: none;
    transition: opacity .15s, transform .1s; font-family: 'Sora', sans-serif;
}
.dl-btn:hover { opacity: .85; transform: translateY(-1px); }
.dl-btn.p { background: #2563eb; color: #fff; }
.dl-btn.g { background: #16a34a; color: #fff; }
.dl-btn.s { background: #f3f4f6; color: #374151; }

/* ── Spinner ── */
@keyframes spin { to { transform: rotate(360deg); } }
.spin { animation: spin .7s linear infinite; }

/* ── Field label/hint ── */
.fl { font-size: 13px; font-weight: 600; color: #2d2d2d; margin: 0 0 3px; display: block; }
.fh { font-size: 12px; color: #aaa; margin: 0 0 8px; line-height: 1.5; }

/* ── File preview ── */
.preview-card { animation: fadeSlide .25s ease; }
@keyframes fadeSlide { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:translateY(0); } }
.preview-table-wrap { overflow-x:auto; border-radius:10px; border:1.5px solid #e8edf2; }
.preview-table { width:100%; border-collapse:collapse; font-size:12px; }
.preview-table th {
    background:#f8fafc; padding:8px 12px; text-align:left;
    font-weight:600; color:#334155; font-size:11px;
    border-bottom:1.5px solid #e2e8f0; white-space:nowrap;
}
.preview-table td {
    padding:7px 12px; color:#475569; border-bottom:1px solid #f1f5f9;
    max-width:160px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
}
.preview-table tr:last-child td { border-bottom:none; }
.preview-table tr:hover td { background:#f8fafc; }
.type-select {
    margin-top:4px; width:100%;
    padding:4px 6px; border:1.5px solid #e2e8f0; border-radius:7px;
    font-size:11px; color:#334155; background:#fff; cursor:pointer;
    font-family:'Sora',sans-serif; outline:none;
    transition:border-color .15s;
}
.type-select:focus { border-color:#2563eb; }
.type-select.overridden { border-color:#2563eb; background:#eff6ff; color:#1e40af; font-weight:600; }

/* ── Progress bar ── */
.progress-wrap {
    margin-top:12px; padding:20px 24px;
    background:#fff; border:1.5px solid #e8edf2; border-radius:16px;
    animation: fadeSlide .25s ease;
}
.progress-track {
    height:8px; background:#f1f5f9; border-radius:8px; overflow:hidden; margin:10px 0 6px;
}
.progress-fill {
    height:100%; border-radius:8px;
    background:linear-gradient(90deg,#3b82f6,#2563eb);
    transition:width .5s ease;
}
.progress-step-label { font-size:13px; font-weight:500; color:#334155; }
.progress-pct-label  { font-size:12px; color:#94a3b8; float:right; margin-top:-20px; }

</style>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Upload Dataset
        </h2>
    </x-slot>

<div class="up">

    @if(session('success'))
    <div style="margin-bottom:12px;padding:12px 16px;background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:12px;font-size:13px;color:#15803d;font-family:'Sora',sans-serif;">
        {{ session('success') }}
    </div>
    @endif

    <form id="uploadForm" enctype="multipart/form-data" novalidate>
        @csrf

        {{-- ── CARD 1: Main file ── --}}
        <div class="card">
            <p class="sec-label">Main file</p>

            <div class="drop-zone" id="dropZone" tabindex="0" role="button" aria-label="Upload file">
                <input type="file" id="fileInput" name="file" accept=".xlsx,.xls,.csv,.txt,.json,.xml">

                <div id="dz-default" style="text-align:center;pointer-events:none;user-select:none;">
                    <svg width="28" height="28" fill="none" stroke="#bfdbfe" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 8px;display:block;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                    </svg>
                    <p style="font-size:13px;color:#555;margin:0;"><span style="color:#2563eb;font-weight:600;">Click to upload</span> or drag &amp; drop</p>
                    <p class="mono" style="font-size:11px;color:#bbb;margin:4px 0 0;">{{ implode(' · ', $supportedFormats) }} · max 20 MB</p>
                </div>

                <div id="dz-selected" style="display:none;text-align:center;pointer-events:none;user-select:none;">
                    <svg width="22" height="22" fill="none" stroke="#2563eb" stroke-width="2" viewBox="0 0 24 24" style="margin:0 auto 6px;display:block;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                    </svg>
                    <p class="mono" style="font-size:13px;font-weight:500;color:#1e40af;margin:0;" id="dz-filename"></p>
                    <p class="mono" style="font-size:11px;color:#93c5fd;margin:3px 0 0;" id="dz-filesize"></p>
                </div>
            </div>
        </div>

        {{-- ── CARD 3: Reference files (only shown in full pipeline mode) ── --}}
        <div class="card" id="refCard" style="display:none;">
            <p class="sec-label">Reference files</p>
            <p class="fh" style="margin-bottom:10px;">Products catalogue, customer list, lookup tables — used for cross-referencing and enrichment.</p>

            <label class="ref-add-btn">
                <input type="file" id="refPicker" accept=".xlsx,.xls,.csv" multiple style="position:absolute;inset:0;opacity:0;cursor:pointer;">
                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
                Add reference file
            </label>

            <div class="ref-chips" id="refChips"></div>
            <div id="refHidden"></div>
        </div>

        {{-- ── Preview card (shown after file selected) ── --}}
        <div id="previewCard" class="card preview-card" style="display:none;margin-bottom:12px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                <p class="sec-label" style="margin:0;">File preview</p>
                <span style="font-size:11px;color:#94a3b8;">First 5 rows · set column types below headers</span>
            </div>
            <div class="preview-table-wrap">
                <table class="preview-table" id="previewTable"></table>
            </div>
            <p style="font-size:11.5px;color:#94a3b8;margin:10px 0 0;">
                ℹ️ Leave on <b>Auto-detect</b> unless the pipeline misidentifies a column type.
            </p>
        </div>

        {{-- ── CARD 2: Pipeline mode selector ── --}}
        <input type="hidden" name="pipeline_mode" id="pipelineMode" value="clean_only">
        <div class="card">
            <p class="sec-label">Pipeline mode</p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">

                <div class="mode-btn selected" id="btn-clean" onclick="setMode('clean_only')">
                    <div class="mode-btn-icon" id="icon-clean">
                        <svg width="18" height="18" fill="none" stroke="#2563eb" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.3 24.3 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23-.693L5 14.5m14.8.8l1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0112 21a48.25 48.25 0 01-8.135-.687c-1.718-.293-2.3-2.379-1.067-3.61L5 14.5"/>
                        </svg>
                    </div>
                    <div class="mode-btn-title">Clean only</div>
                    <div class="mode-btn-desc">Standardise dates, prices and formats. Fast, no merging.</div>
                </div>

                <div class="mode-btn" id="btn-full" onclick="setMode('full_pipeline')">
                    <div class="mode-btn-icon" id="icon-full">
                        <svg width="18" height="18" fill="none" stroke="#2563eb" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0020.25 18V6A2.25 2.25 0 0018 3.75H6A2.25 2.25 0 003.75 6v12A2.25 2.25 0 006 20.25z"/>
                        </svg>
                    </div>
                    <div class="mode-btn-title">Full pipeline</div>
                    <div class="mode-btn-desc">Merge reference files, deduplicate, validate, enrich and clean.</div>
                </div>

            </div>
        </div>

        {{-- ── CARD 4: Dataset context (collapsible) ── --}}
        <div class="card">
            <button type="button" class="ctx-toggle" id="ctxToggle">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:32px;height:32px;border-radius:10px;background:#eff6ff;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg width="15" height="15" fill="none" stroke="#3b82f6" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z"/>
                        </svg>
                    </div>
                    <div>
                        <div style="font-size:13px;font-weight:600;color:#1a1a1a;">Dataset context</div>
                        <div style="font-size:11.5px;color:#aaa;margin-top:1px;">Help the AI make smarter decisions</div>
                    </div>
                </div>
                <div class="ctx-arrow" id="ctxArrow">
                    <svg width="12" height="12" fill="none" stroke="#888" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                    </svg>
                </div>
            </button>

            <div id="ctxPanel" style="display:none;margin-top:20px;">
                <div class="divider" style="margin-top:0;"></div>

                <div style="margin-bottom:16px;">
                    <label class="fl">What is this dataset about?</label>
                    <p class="fh">Domain context helps the AI understand whether values are valid — e.g. can a temperature be negative?</p>
                    <textarea name="dataset_description" rows="2" maxlength="1000"
                              placeholder="e.g. Monthly sales orders with product SKUs and customer details from our ERP system"
                              class="u-textarea"></textarea>
                </div>

                <div style="margin-bottom:16px;">
                    <label class="fl">Columns that must always be positive</label>
                    <p class="fh">Negative values will be auto-corrected (sign error assumed).</p>
                    <div id="nnCont" style="display:flex;flex-direction:column;gap:6px;">
                        <div style="display:flex;gap:6px;align-items:center;">
                            <input type="text" name="no_negative_cols[]" placeholder="e.g. quantity, unit_price" maxlength="100" class="u-input">
                            <button type="button" class="add-row-btn" onclick="addTxtRow('nnCont','no_negative_cols[]','e.g. stock_count')">+ Add</button>
                        </div>
                    </div>
                </div>

                <div style="margin-bottom:16px;">
                    <label class="fl">Unique identifier columns</label>
                    <p class="fh">IDs, emails, codes — never imputed or modified by the pipeline.</p>
                    <div id="idCont" style="display:flex;flex-direction:column;gap:6px;">
                        <div style="display:flex;gap:6px;align-items:center;">
                            <input type="text" name="identifier_cols[]" placeholder="e.g. customer_id, order_id" maxlength="100" class="u-input">
                            <button type="button" class="add-row-btn" onclick="addTxtRow('idCont','identifier_cols[]','e.g. sku, product_code')">+ Add</button>
                        </div>
                    </div>
                </div>

                <div style="margin-bottom:4px;">
                    <label class="fl">Known valid ranges</label>
                    <p class="fh">Values outside these ranges are flagged for review. e.g. age: 0–120</p>
                    <div id="rangeCont" style="display:flex;flex-direction:column;gap:6px;">
                        <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                            <input type="text"   name="range_rules[0][column]" placeholder="Column" maxlength="100" style="flex:2;min-width:80px;" class="u-input">
                            <span style="font-size:11px;color:#bbb;">min</span>
                            <input type="number" name="range_rules[0][min]" placeholder="0"   style="flex:1;min-width:58px;" class="u-input">
                            <span style="font-size:11px;color:#bbb;">max</span>
                            <input type="number" name="range_rules[0][max]" placeholder="100" style="flex:1;min-width:58px;" class="u-input">
                            <button type="button" class="add-row-btn" onclick="addRangeRow()">+ Add</button>
                        </div>
                    </div>
                </div>

                <div class="divider"></div>

                <div class="sw-row">
                    <label class="sw"><input type="checkbox" id="flag_only" name="flag_only" value="1"><span class="sw-track"></span></label>
                    <div>
                        <div style="font-size:13px;font-weight:500;color:#1a1a1a;cursor:pointer;" onclick="document.getElementById('flag_only').click()">Flag only — never auto-correct</div>
                        <div style="font-size:12px;color:#aaa;margin-top:2px;">Suspicious values are flagged for human review instead of being automatically fixed.</div>
                    </div>
                </div>

                <div class="sw-row">
                    <label class="sw"><input type="checkbox" id="use_llm_enricher" name="use_llm_enricher" value="1" checked><span class="sw-track"></span></label>
                    <div>
                        <div style="font-size:13px;font-weight:500;color:#1a1a1a;cursor:pointer;" onclick="document.getElementById('use_llm_enricher').click()">Use AI to predict missing values</div>
                        <div style="font-size:12px;color:#aaa;margin-top:2px;">The AI fills in missing values based on surrounding rows. Disable for faster processing.</div>
                    </div>
                </div>

            </div>
        </div>

        {{-- ── Submit ── --}}
        <div class="submit-row">
            <p class="text-xs text-gray-400 m-0 font-sans">
                @if(isset($planSlug) && $planSlug === 'pro')
                    <span class="font-semibold text-indigo-600">Pro</span> · 20 MB max
                @elseif(isset($planSlug) && $planSlug === 'medium')
                    <span class="font-semibold text-indigo-600">Medium</span> · 10 MB max
                @else
                    <span class="font-semibold text-indigo-600">Free</span> · 2 MB max
                    · <a href="/pricing" class="font-medium text-indigo-600 hover:text-indigo-700">
                        Upgrade now!
                    </a>
                @endif
            </p>
            <div class="flex justify-center items-center mt-6">
                <button type="submit" id="submitBtn"
                class="px-6 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 flex items-center gap-2">
                    <span id="submitLabel">Process file</span>
                    <svg id="submitSpinner" class="spin" style="display:none;width:15px;height:15px;" fill="none" viewBox="0 0 24 24">
                        <circle style="opacity:.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3.5"/>
                        <path style="opacity:.9" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                    </svg>
                </button>
            </div>

        </div>
    </form>


    {{-- Progress bar (shown while processing) --}}
    <div id="progressWrap" class="progress-wrap" style="display:none;">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:2px;">
            <svg class="spin" width="14" height="14" fill="none" viewBox="0 0 24 24" style="flex-shrink:0;">
                <circle style="opacity:.2" cx="12" cy="12" r="10" stroke="#2563eb" stroke-width="3.5"/>
                <path style="opacity:.9" fill="#2563eb" d="M4 12a8 8 0 018-8v8H4z"/>
            </svg>
            <span class="progress-step-label" id="progressLabel">Starting…</span>
        </div>
        <div class="progress-track">
            <div class="progress-fill" id="progressFill" style="width:5%"></div>
        </div>
        <span class="progress-pct-label" id="progressPct">5%</span>
        <div style="clear:both;"></div>
    </div>

    {{-- ── Results ── --}}
    <div id="resultsPanel" style="display:none;margin-top:12px;" class="card">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;">
            <svg width="18" height="18" fill="none" stroke="#16a34a" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span style="font-size:14px;font-weight:600;color:#111;font-family:'Sora',sans-serif;">Processing complete</span>
        </div>
        <div class="chips-row" id="resultStats"></div>
        <div class="dl-row" id="dlButtons"></div>
        <div id="ctxNotice" style="display:none;margin-top:12px;font-size:12px;color:#1d4ed8;background:#eff6ff;border-radius:8px;padding:8px 12px;font-family:'Sora',sans-serif;">
            ✓ Custom validation rules were generated from your dataset context.
        </div>
    </div>

    {{-- ── Error ── --}}
    <div id="errorPanel" style="display:none;margin-top:12px;padding:14px 16px;background:#fff1f2;border:1.5px solid #fecdd3;border-radius:14px;font-family:'Sora',sans-serif;">
        <p style="font-size:13px;font-weight:600;color:#be123c;margin:0 0 3px;">Processing error</p>
        <p id="errorMsg" style="font-size:13px;color:#e11d48;margin:0;"></p>
    </div>

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/PapaParse/5.4.1/papaparse.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
(function(){
'use strict';

// ─────────────────────────────────────────────────────────────────────────────
// DROP ZONE
// ─────────────────────────────────────────────────────────────────────────────
var dz    = document.getElementById('dropZone');
var fi    = document.getElementById('fileInput');
var dzDef = document.getElementById('dz-default');
var dzSel = document.getElementById('dz-selected');
var dzFn  = document.getElementById('dz-filename');
var dzFs  = document.getElementById('dz-filesize');

function fmtBytes(b){ return b<1024?b+' B':b<1048576?(b/1024).toFixed(1)+' KB':(b/1048576).toFixed(1)+' MB'; }

function showMainFile(f){
    dzDef.style.display='none'; dzSel.style.display='block';
    dzFn.textContent=f.name; dzFs.textContent=fmtBytes(f.size);
    dz.classList.add('has-file'); dz.classList.remove('dragover');
    parseAndPreview(f);
}

fi.addEventListener('change', function(){ if(this.files[0]) showMainFile(this.files[0]); });
dz.addEventListener('dragover',  function(e){ e.preventDefault(); dz.classList.add('dragover'); });
dz.addEventListener('dragleave', function(){ dz.classList.remove('dragover'); });
dz.addEventListener('drop', function(e){
    e.preventDefault(); dz.classList.remove('dragover');
    if(e.dataTransfer.files[0]){
        try{ var dt=new DataTransfer(); dt.items.add(e.dataTransfer.files[0]); fi.files=dt.files; }catch(x){}
        showMainFile(e.dataTransfer.files[0]);
    }
});
dz.addEventListener('keydown', function(e){ if(e.key==='Enter'||e.key===' '){ e.preventDefault(); fi.click(); }});

// ─────────────────────────────────────────────────────────────────────────────
// FILE PREVIEW + COLUMN TYPE PICKER
// ─────────────────────────────────────────────────────────────────────────────
var columnTypes = {}; // {colName: typeString}

var TYPE_OPTIONS = [
    ['auto',       '🔍 Auto-detect'],
    ['date',       '📅 Date'],
    ['price',      '💰 Price / Currency'],
    ['integer',    '🔢 Integer'],
    ['text',       '💬 Text'],
    ['identifier', '🔑 Identifier / ID'],
];

function parseAndPreview(file) {
    var ext = file.name.split('.').pop().toLowerCase();
    try {
        if (ext === 'csv' || ext === 'txt') {
            if (typeof Papa === 'undefined') {
                console.warn('PapaParse not loaded yet'); return;
            }
            Papa.parse(file, {
                preview: 6,
                skipEmptyLines: true,
                complete: function(res) { renderPreview(res.data); }
            });
        } else if (ext === 'xlsx' || ext === 'xls') {
            if (typeof XLSX === 'undefined') {
                console.warn('SheetJS not loaded yet'); return;
            }
            var reader = new FileReader();
            reader.onload = function(e) {
                try {
                    var wb   = XLSX.read(e.target.result, {type:'array'});
                    var ws   = wb.Sheets[wb.SheetNames[0]];
                    var data = XLSX.utils.sheet_to_json(ws, {header:1, defval:''});
                    renderPreview(data.slice(0, 6));
                } catch(ex) { console.error('XLSX parse error', ex); }
            };
            reader.readAsArrayBuffer(file);
        } else {
            document.getElementById('previewCard').style.display = 'none';
        }
    } catch(ex) { console.error('parseAndPreview error', ex); }
}

function renderPreview(rows) {
    if (!rows || rows.length < 2) {
        document.getElementById('previewCard').style.display = 'none';
        return;
    }

    var headers  = rows[0];
    var dataRows = rows.slice(1);
    columnTypes  = {};

    var thead = '<thead><tr>';
    headers.forEach(function(h) {
        var col = String(h).trim();
        var opts = TYPE_OPTIONS.map(function(o) {
            return '<option value="'+o[0]+'">'+o[1]+'</option>';
        }).join('');
        thead += '<th>' +
            '<div>'+escHtml(col)+'</div>' +
            '<select class="type-select" data-col="'+escHtml(col)+'" onchange="onTypeChange(this)">' +
            opts + '</select>' +
            '</th>';
    });
    thead += '</tr></thead>';

    var tbody = '<tbody>';
    dataRows.forEach(function(row) {
        tbody += '<tr>';
        headers.forEach(function(_, i) {
            var val = row[i] !== undefined ? String(row[i]) : '';
            tbody += '<td title="'+escHtml(val)+'">'+escHtml(val.length > 22 ? val.slice(0,22)+'…' : val)+'</td>';
        });
        tbody += '</tr>';
    });
    tbody += '</tbody>';

    document.getElementById('previewTable').innerHTML = thead + tbody;
    document.getElementById('previewCard').style.display = 'block';
}

window.onTypeChange = function(sel) {
    var col = sel.dataset.col;
    columnTypes[col] = sel.value;
    sel.classList.toggle('overridden', sel.value !== 'auto');
};

function escHtml(s) {
    return String(s)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function buildColumnTypeInputs() {
    // Append hidden inputs for column_types to the form before submit
    document.querySelectorAll('.col-type-hidden').forEach(function(el){ el.remove(); });
    Object.keys(columnTypes).forEach(function(col) {
        var val = columnTypes[col];
        if (val && val !== 'auto') {
            var inp = document.createElement('input');
            inp.type  = 'hidden';
            inp.name  = 'column_types[' + col + ']';
            inp.value = val;
            inp.className = 'col-type-hidden';
            document.getElementById('uploadForm').appendChild(inp);
        }
    });
}

// ─────────────────────────────────────────────────────────────────────────────
// REFERENCE FILES
// ─────────────────────────────────────────────────────────────────────────────
var refPicker  = document.getElementById('refPicker');
var refChips   = document.getElementById('refChips');
var refHidden  = document.getElementById('refHidden');
var refFiles   = [];

refPicker.addEventListener('change', function(){
    Array.from(this.files).forEach(function(f){
        if(refFiles.some(function(r){ return r.name===f.name; })) return;
        refFiles.push({name: f.name, file: f});
        addRefChip(f.name);
        rebuildHiddenInputs();
    });
    refPicker.value='';
});

function addRefChip(name){
    var chip = document.createElement('div');
    chip.className = 'ref-chip'; chip.dataset.name = name;
    chip.innerHTML =
        '<svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>' +
        '<span>' + name + '</span>' +
        '<button type="button" onclick="removeRef(this,\''+name+'\')" aria-label="Remove">' +
        '<svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>';
    refChips.appendChild(chip);
}

window.removeRef = function(btn, name){
    refFiles = refFiles.filter(function(r){ return r.name!==name; });
    btn.closest('.ref-chip').remove();
    rebuildHiddenInputs();
    updatePipelineMode();
};

function rebuildHiddenInputs(){
    refHidden.innerHTML='';
    var dt = new DataTransfer();
    refFiles.forEach(function(r){ dt.items.add(r.file); });
    var inp = document.createElement('input');
    inp.type='file'; inp.name='reference_files[]'; inp.multiple=true;
    inp.style.display='none';
    try{ inp.files = dt.files; }catch(x){}
    refHidden.appendChild(inp);
}

// ─────────────────────────────────────────────────────────────────────────────
// PIPELINE MODE TOGGLE
// ─────────────────────────────────────────────────────────────────────────────
window.setMode = function(mode) {
    document.getElementById('pipelineMode').value = mode;
    var btnClean  = document.getElementById('btn-clean');
    var btnFull   = document.getElementById('btn-full');
    var refCard   = document.getElementById('refCard');
    if (mode === 'full_pipeline') {
        btnFull.classList.add('selected');   btnClean.classList.remove('selected');
        document.getElementById('icon-full').classList.add('active');
        document.getElementById('icon-clean').classList.remove('active');
        refCard.style.display = 'block';
    } else {
        btnClean.classList.add('selected');  btnFull.classList.remove('selected');
        document.getElementById('icon-clean').classList.add('active');
        document.getElementById('icon-full').classList.remove('active');
        refCard.style.display = 'none';
        refFiles = [];
        document.getElementById('refChips').innerHTML = '';
        document.getElementById('refHidden').innerHTML = '';
    }
};
function updatePipelineMode(){}

// ─────────────────────────────────────────────────────────────────────────────
// CONTEXT TOGGLE
// ─────────────────────────────────────────────────────────────────────────────
var ctxToggle = document.getElementById('ctxToggle');
var ctxPanel  = document.getElementById('ctxPanel');
var ctxArrow  = document.getElementById('ctxArrow');
var ctxOpen   = false;
ctxToggle.addEventListener('click', function(){
    ctxOpen = !ctxOpen;
    ctxPanel.style.display = ctxOpen ? 'block' : 'none';
    ctxArrow.classList.toggle('open', ctxOpen);
});

// ─────────────────────────────────────────────────────────────────────────────
// DYNAMIC ROWS
// ─────────────────────────────────────────────────────────────────────────────
window.addTxtRow = function(cid, name, ph){
    var c=document.getElementById(cid), d=document.createElement('div');
    d.style.cssText='display:flex;gap:6px;align-items:center;';
    d.innerHTML='<input type="text" name="'+name+'" placeholder="'+ph+'" maxlength="100" class="u-input">'+
        '<button type="button" class="rm-btn" onclick="this.closest(\'div\').remove()">×</button>';
    c.appendChild(d);
};
var ri=1;
window.addRangeRow = function(){
    var i=ri++, c=document.getElementById('rangeCont'), d=document.createElement('div');
    d.style.cssText='display:flex;gap:6px;align-items:center;flex-wrap:wrap;';
    d.innerHTML=
        '<input type="text" name="range_rules['+i+'][column]" placeholder="Column" maxlength="100" style="flex:2;min-width:80px;" class="u-input">'+
        '<span style="font-size:11px;color:#bbb;">min</span>'+
        '<input type="number" name="range_rules['+i+'][min]" placeholder="0" style="flex:1;min-width:58px;" class="u-input">'+
        '<span style="font-size:11px;color:#bbb;">max</span>'+
        '<input type="number" name="range_rules['+i+'][max]" placeholder="100" style="flex:1;min-width:58px;" class="u-input">'+
        '<button type="button" class="rm-btn" onclick="this.closest(\'div\').remove()">×</button>';
    c.appendChild(d);
};

// ─────────────────────────────────────────────────────────────────────────────
// PROGRESS BAR POLLING
// ─────────────────────────────────────────────────────────────────────────────
var pollInterval = null;

function startPolling(jobId) {
    var progressWrap  = document.getElementById('progressWrap');
    var progressFill  = document.getElementById('progressFill');
    var progressLabel = document.getElementById('progressLabel');
    var progressPct   = document.getElementById('progressPct');

    progressWrap.style.display = 'block';

    // Animate through steps visually even if job finishes fast
    var startTime = Date.now();
    var MIN_DISPLAY_MS = 2800; // always show progress for at least this long

    var FAKE_STEPS = [
        { pct: 10, label: 'Saving files…'                },
        { pct: 30, label: 'Cleaning & standardising…'    },
        { pct: 55, label: 'Validating data…'             },
        { pct: 75, label: 'AI enrichment…'               },
        { pct: 88, label: 'Generating PDF report…'       },
    ];
    var fakeIdx = 0;

    // Animate fake steps every 500ms
    var animInterval = setInterval(function() {
        if (fakeIdx < FAKE_STEPS.length) {
            var step = FAKE_STEPS[fakeIdx++];
            progressFill.style.width  = step.pct + '%';
            progressPct.textContent   = step.pct + '%';
            progressLabel.textContent = step.label;
        }
    }, 500);

    var pendingResult = null;
    var pendingError  = null;

    function finish() {
        clearInterval(animInterval);
        clearInterval(pollInterval);

        // Snap to 100%
        progressFill.style.width  = '100%';
        progressPct.textContent   = '100%';
        progressLabel.textContent = 'Complete';

        setTimeout(function() {
            progressWrap.style.display = 'none';
            if (pendingError) {
                showError(pendingError);
            } else {
                showResults(pendingResult);
            }
            resetSubmitBtn();
        }, 400);
    }

    pollInterval = setInterval(function() {
        fetch('{{ url("datasets/jobs") }}/' + jobId + '/status', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r){ return r.json(); })
        .then(function(j){
            if (j.status === 'done') {
                pendingResult = j.result;
                var elapsed = Date.now() - startTime;
                var wait = Math.max(0, MIN_DISPLAY_MS - elapsed);
                setTimeout(finish, wait);
                clearInterval(pollInterval);
            } else if (j.status === 'failed') {
                pendingError = j.error || 'Pipeline failed.';
                var elapsed = Date.now() - startTime;
                var wait = Math.max(0, MIN_DISPLAY_MS - elapsed);
                setTimeout(finish, wait);
                clearInterval(pollInterval);
            }
        })
        .catch(function(){ /* keep polling silently on network glitch */ });
    }, 1000);
}

// ─────────────────────────────────────────────────────────────────────────────
// FORM SUBMIT
// ─────────────────────────────────────────────────────────────────────────────
var form       = document.getElementById('uploadForm');
var submitBtn  = document.getElementById('submitBtn');
var submitLbl  = document.getElementById('submitLabel');
var submitSpin = document.getElementById('submitSpinner');
var resPanel   = document.getElementById('resultsPanel');
var resStats   = document.getElementById('resultStats');
var dlButtons  = document.getElementById('dlButtons');
var ctxNotice  = document.getElementById('ctxNotice');
var errPanel   = document.getElementById('errorPanel');
var errMsg     = document.getElementById('errorMsg');

function resetSubmitBtn(){
    submitBtn.disabled = false;
    submitLbl.textContent = 'Process file';
    submitSpin.style.display = 'none';
}

form.addEventListener('submit', function(e){
    e.preventDefault();
    if(!fi.files[0]){ alert('Please select a file first.'); return; }

    buildColumnTypeInputs();

    submitBtn.disabled = true;
    submitLbl.textContent = 'Uploading…';
    submitSpin.style.display = 'block';
    resPanel.style.display = 'none';
    errPanel.style.display = 'none';
    if(pollInterval) clearInterval(pollInterval);

    fetch('{{ route("datasets.upload") }}', {
        method:'POST',
        headers:{'X-Requested-With':'XMLHttpRequest'},
        body: new FormData(form),
    })
    .then(function(r){ return r.json(); })
    .then(function(j){
        if (j.status === 'queued') {
            submitLbl.textContent = 'Processing…';
            startPolling(j.job_id);
        } else if (j.status === 'error') {
            showError(j.message || 'An unexpected error occurred.');
            resetSubmitBtn();
        }
    })
    .catch(function(err){
        showError('Network error: ' + err.message);
        resetSubmitBtn();
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// RESULTS RENDERING
// ─────────────────────────────────────────────────────────────────────────────
function chip(lbl, val, cls){
    return '<div class="s-chip '+cls+'"><div class="v">'+val+'</div><div class="l">'+lbl+'</div></div>';
}
function dlBtn(lbl, url, cls){
    return '<a href="'+url+'" download class="dl-btn '+cls+'">' +
        '<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>' +
        lbl+'</a>';
}

function showResults(json){
    var d=json.data||{}, u=json.download_urls||{};
    var rows  = d.final_rows     != null ? d.final_rows     : d.rows;
    var cols  = d.final_cols     != null ? d.final_cols     : d.columns;
    var nulls = d.null_remaining != null ? d.null_remaining : null;
    var dedup = d.dedup_after_merge || 0;
    var chips=[];
    if(rows  != null) chips.push(chip('Output rows',        Number(rows).toLocaleString(), 'blue'));
    if(cols  != null) chips.push(chip('Columns',            cols,                          ''));
    if(nulls != null) chips.push(chip('NULLs remaining',   nulls,                         nulls>0?'amber':'green'));
    if(dedup  > 0)    chips.push(chip('Duplicates removed', dedup,                         'amber'));
    resStats.innerHTML=chips.join('');

    var btns=[];
    if(u.cleaned)    btns.push(dlBtn('Cleaned File',  u.cleaned,    'p'));
    if(u.enriched)   btns.push(dlBtn('Enriched File', u.enriched,   'p'));
    if(u.report_pdf) btns.push(dlBtn('PDF Report',    u.report_pdf, 'g'));
    if(u.report)     btns.push(dlBtn('JSON Report',   u.report,     's'));
    var vizFile = u.enriched || u.cleaned;

    if(vizFile) {
        var vizFilename = vizFile.split('/').pop().split('?')[0];
        var vizUrl = '/import/from-cleaned/' + encodeURIComponent(vizFilename);
        btns.push('<a href="' + vizUrl + '" class="dl-btn" style="background:#7c3aed;color:#fff;">' +
            '<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>' +
            'Visualise</a>');
    }
    dlButtons.innerHTML=btns.join('');

    ctxNotice.style.display=json.context_rules_applied?'block':'none';
    resPanel.style.display='block';
    resPanel.scrollIntoView({behavior:'smooth',block:'start'});
}

function showError(msg){
    errMsg.textContent=msg;
    errPanel.style.display='block';
    errPanel.scrollIntoView({behavior:'smooth',block:'start'});
}

})();
</script>

</x-app-layout>
