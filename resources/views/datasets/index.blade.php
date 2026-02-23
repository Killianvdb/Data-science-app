<x-app-layout>

<div class="max-w-3xl mx-auto">

    {{-- ── Page header ─────────────────────────────────────────────────────── --}}
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900">Upload Dataset</h1>
        <p class="mt-1 text-sm text-gray-500">
            Upload your file and optionally describe your data so the pipeline
            can make smarter decisions.
        </p>
    </div>

    {{-- ── Flash messages ──────────────────────────────────────────────────── --}}
    @if (session('success'))
        <div class="mb-6 rounded-lg bg-green-50 border border-green-200 p-4 text-green-800 text-sm">
            {{ session('success') }}
        </div>
    @endif

    {{-- ── Form ────────────────────────────────────────────────────────────── --}}
    <form id="uploadForm" enctype="multipart/form-data" novalidate>
        @csrf

        {{-- CARD 1 — File selection --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-5">
            <h2 class="text-sm font-semibold text-gray-900 mb-4 flex items-center gap-2">
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-blue-600 text-white text-xs font-bold">1</span>
                Select your file
            </h2>

            <div id="dropZone"
                 class="relative flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-gray-300 rounded-lg bg-gray-50 cursor-pointer transition-colors"
                 role="button" tabindex="0" aria-label="Upload area">

                <input type="file" id="fileInput" name="file"
                       accept=".xlsx,.xls,.csv,.txt,.json,.xml"
                       class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">

                <div id="dropDefault" class="text-center pointer-events-none select-none">
                    <svg class="mx-auto mb-2 w-7 h-7 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                    <p class="text-sm text-gray-600">
                        <span class="font-medium text-blue-600">Click to upload</span> or drag &amp; drop
                    </p>
                    <p class="text-xs text-gray-400 mt-1">
                        Supported: {{ implode(', ', $supportedFormats) }} &middot; Max 20 MB
                    </p>
                </div>

                <div id="dropSelected" class="hidden text-center pointer-events-none select-none">
                    <svg class="mx-auto mb-1 w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <p class="text-sm font-medium text-gray-800" id="selectedFileName"></p>
                    <p class="text-xs text-gray-400" id="selectedFileSize"></p>
                </div>
            </div>

            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Reference files
                    <span class="text-gray-400 font-normal">(optional &mdash; hold Ctrl/Cmd to select multiple)</span>
                </label>
                <input type="file" name="reference_files[]" id="referenceFiles"
                       multiple accept=".xlsx,.xls,.csv"
                       class="block w-full text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
                <p class="mt-1 text-xs text-gray-400" id="refFilesLabel">
                    Products catalogue, customer list, etc. Used to enrich missing values.
                </p>
            </div>

            <div class="mt-4 flex items-center gap-4">
                <span class="text-sm font-medium text-gray-700">Mode:</span>
                <label class="flex items-center gap-1.5 cursor-pointer">
                    <input type="radio" name="pipeline_mode" value="full_pipeline" checked class="text-blue-600 focus:ring-blue-500">
                    <span class="text-sm text-gray-700">Full pipeline</span>
                </label>
                <label class="flex items-center gap-1.5 cursor-pointer">
                    <input type="radio" name="pipeline_mode" value="clean_only" class="text-blue-600 focus:ring-blue-500">
                    <span class="text-sm text-gray-700">Clean only</span>
                </label>
            </div>
        </div>

        {{-- CARD 2 — Context (collapsible) --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-5">

            <button type="button" id="toggleContext"
                    class="w-full flex items-center justify-between text-left focus:outline-none">
                <span class="text-sm font-semibold text-gray-900 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-blue-600 text-white text-xs font-bold">2</span>
                    Tell us about your data
                    <span class="text-xs font-normal text-gray-400">(optional)</span>
                </span>
                <span id="toggleIcon" class="text-gray-400 text-lg select-none">&#8595;</span>
            </button>
            <p class="mt-1 text-xs text-gray-400 ml-7">
                The more you tell us, the smarter the cleaning. No technical knowledge needed.
            </p>

            <div id="contextPanel" class="hidden mt-5 space-y-5">

                <div>
                    <label for="dataset_description" class="block text-sm font-medium text-gray-700 mb-1">
                        What is this dataset about?
                    </label>
                    <textarea id="dataset_description" name="dataset_description"
                              rows="2" maxlength="1000"
                              placeholder="e.g. Monthly sales orders with product SKUs and customer details"
                              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 resize-none"></textarea>
                    <p class="mt-1 text-xs text-gray-400">
                        Helps the AI understand context &mdash; e.g. whether a temperature column can be negative.
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Which columns should always be positive?
                    </label>
                    <p class="text-xs text-gray-400 mb-2">Negative values will be auto-corrected (sign error assumed).</p>
                    <div id="noNegativeContainer" class="space-y-2">
                        <div class="flex gap-2 items-center">
                            <input type="text" name="no_negative_cols[]"
                                   placeholder="Column name, e.g. quantity" maxlength="100"
                                   class="flex-1 rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            <button type="button" onclick="addTextRow('noNegativeContainer','no_negative_cols[]','Column name, e.g. salary')"
                                    class="shrink-0 text-xs text-blue-600 hover:text-blue-800 font-medium">+ Add</button>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Which columns are unique identifiers?
                    </label>
                    <p class="text-xs text-gray-400 mb-2">IDs, emails, codes &mdash; never imputed or modified.</p>
                    <div id="identifierContainer" class="space-y-2">
                        <div class="flex gap-2 items-center">
                            <input type="text" name="identifier_cols[]"
                                   placeholder="Column name, e.g. customer_id" maxlength="100"
                                   class="flex-1 rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            <button type="button" onclick="addTextRow('identifierContainer','identifier_cols[]','Column name, e.g. order_id')"
                                    class="shrink-0 text-xs text-blue-600 hover:text-blue-800 font-medium">+ Add</button>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Known valid ranges?</label>
                    <p class="text-xs text-gray-400 mb-2">Values outside these ranges will be flagged. Example: age min 0 max 120.</p>
                    <div id="rangeContainer" class="space-y-2">
                        <div class="flex flex-wrap gap-2 items-center">
                            <input type="text"   name="range_rules[0][column]" placeholder="Column" maxlength="100"
                                   class="w-28 rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            <span class="text-xs text-gray-500">min</span>
                            <input type="number" name="range_rules[0][min]" placeholder="0"
                                   class="w-20 rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            <span class="text-xs text-gray-500">max</span>
                            <input type="number" name="range_rules[0][max]" placeholder="100"
                                   class="w-20 rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            <button type="button" onclick="addRangeRow()"
                                    class="shrink-0 text-xs text-blue-600 hover:text-blue-800 font-medium">+ Add</button>
                        </div>
                    </div>
                </div>

                <div class="flex items-start gap-3 pt-3 border-t border-gray-100">
                    <input type="checkbox" id="flag_only" name="flag_only" value="1"
                           class="mt-0.5 w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <div>
                        <label for="flag_only" class="text-sm font-medium text-gray-700 cursor-pointer">Flag only &mdash; never auto-correct</label>
                        <p class="text-xs text-gray-400">All suspicious values are flagged for human review instead of being fixed.</p>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <input type="checkbox" id="use_llm_enricher" name="use_llm_enricher" value="1" checked
                           class="mt-0.5 w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <div>
                        <label for="use_llm_enricher" class="text-sm font-medium text-gray-700 cursor-pointer">Use AI to predict missing values</label>
                        <p class="text-xs text-gray-400">The AI fills in missing values based on surrounding rows. Disable for faster processing.</p>
                    </div>
                </div>

            </div>
        </div>

        {{-- Submit --}}
        <div class="flex items-center justify-between">
            <p class="text-xs text-gray-400">
                @if(isset($planSlug) && $planSlug === 'pro')
                    Pro plan &middot; 20 MB max per file
                @else
                    Free plan &middot; upgrade for larger files and batch processing
                @endif
            </p>
            <button type="submit" id="submitBtn"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-sm font-semibold rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                <span id="submitLabel">Process file</span>
                <svg id="submitSpinner" class="hidden animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                </svg>
            </button>
        </div>
    </form>

    {{-- Results --}}
    <div id="resultsPanel" class="hidden mt-8 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-sm font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            Processing complete
        </h2>
        <div id="resultStats" class="flex flex-wrap gap-3 mb-5"></div>
        <div id="downloadButtons" class="flex flex-wrap gap-3"></div>
        <div id="contextRulesNotice" class="hidden mt-3 text-xs text-blue-700 bg-blue-50 rounded-md px-3 py-2">
            Your dataset context was applied &mdash; custom validation rules were generated from your form.
        </div>
    </div>

    {{-- Error --}}
    <div id="errorPanel" class="hidden mt-6 bg-red-50 border border-red-200 rounded-lg p-4">
        <p class="text-sm font-semibold text-red-800">Processing error</p>
        <p id="errorMessage" class="text-sm text-red-600 mt-1"></p>
    </div>

</div>

<script>
(function () {
    'use strict';

    // Drop zone
    var dropZone    = document.getElementById('dropZone');
    var fileInput   = document.getElementById('fileInput');
    var dropDefault = document.getElementById('dropDefault');
    var dropSelected = document.getElementById('dropSelected');
    var selectedFileName = document.getElementById('selectedFileName');
    var selectedFileSize = document.getElementById('selectedFileSize');
    var refFilesLabel    = document.getElementById('refFilesLabel');
    var referenceFiles   = document.getElementById('referenceFiles');

    function formatBytes(b) {
        if (b < 1024) return b + ' B';
        if (b < 1048576) return (b/1024).toFixed(1) + ' KB';
        return (b/1048576).toFixed(1) + ' MB';
    }

    function showFile(file) {
        dropDefault.classList.add('hidden');
        dropSelected.classList.remove('hidden');
        selectedFileName.textContent = file.name;
        selectedFileSize.textContent = formatBytes(file.size);
        dropZone.classList.add('border-blue-400', 'bg-blue-50');
    }

    fileInput.addEventListener('change', function () {
        if (this.files[0]) showFile(this.files[0]);
    });

    dropZone.addEventListener('dragover', function (e) {
        e.preventDefault();
        dropZone.classList.add('border-blue-400', 'bg-blue-50');
    });
    dropZone.addEventListener('dragleave', function () {
        if (!fileInput.files[0]) dropZone.classList.remove('border-blue-400', 'bg-blue-50');
    });
    dropZone.addEventListener('drop', function (e) {
        e.preventDefault();
        if (e.dataTransfer.files[0]) {
            try {
                var dt = new DataTransfer();
                dt.items.add(e.dataTransfer.files[0]);
                fileInput.files = dt.files;
            } catch(err) {}
            showFile(e.dataTransfer.files[0]);
        }
    });
    dropZone.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fileInput.click(); }
    });

    referenceFiles.addEventListener('change', function () {
        var n = this.files.length;
        refFilesLabel.textContent = n === 0
            ? 'Products catalogue, customer list, etc. Used to enrich missing values.'
            : n + ' file' + (n > 1 ? 's' : '') + ' selected.';
    });

    // Context panel toggle
    var toggleBtn    = document.getElementById('toggleContext');
    var contextPanel = document.getElementById('contextPanel');
    var toggleIcon   = document.getElementById('toggleIcon');
    var panelOpen    = false;

    toggleBtn.addEventListener('click', function () {
        panelOpen = !panelOpen;
        if (panelOpen) {
            contextPanel.classList.remove('hidden');
            toggleIcon.innerHTML = '&#8593;';
        } else {
            contextPanel.classList.add('hidden');
            toggleIcon.innerHTML = '&#8595;';
        }
    });

    // Dynamic rows
    window.addTextRow = function (containerId, fieldName, placeholder) {
        var container = document.getElementById(containerId);
        var row = document.createElement('div');
        row.className = 'flex gap-2 items-center';
        row.innerHTML = '<input type="text" name="' + fieldName + '" placeholder="' + placeholder + '" maxlength="100" ' +
            'class="flex-1 rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">' +
            '<button type="button" onclick="this.closest(\'div\').remove()" ' +
            'class="shrink-0 text-xs text-red-400 hover:text-red-600 font-medium">Remove</button>';
        container.appendChild(row);
    };

    var rangeIdx = 1;
    window.addRangeRow = function () {
        var i = rangeIdx++;
        var container = document.getElementById('rangeContainer');
        var row = document.createElement('div');
        row.className = 'flex flex-wrap gap-2 items-center';
        row.innerHTML =
            '<input type="text" name="range_rules[' + i + '][column]" placeholder="Column" maxlength="100" ' +
            'class="w-28 rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">' +
            '<span class="text-xs text-gray-500">min</span>' +
            '<input type="number" name="range_rules[' + i + '][min]" placeholder="0" ' +
            'class="w-20 rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">' +
            '<span class="text-xs text-gray-500">max</span>' +
            '<input type="number" name="range_rules[' + i + '][max]" placeholder="100" ' +
            'class="w-20 rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">' +
            '<button type="button" onclick="this.closest(\'div\').remove()" ' +
            'class="shrink-0 text-xs text-red-400 hover:text-red-600 font-medium">Remove</button>';
        container.appendChild(row);
    };

    // Form submit
    var form          = document.getElementById('uploadForm');
    var submitBtn     = document.getElementById('submitBtn');
    var submitLabel   = document.getElementById('submitLabel');
    var submitSpinner = document.getElementById('submitSpinner');
    var resultsPanel  = document.getElementById('resultsPanel');
    var resultStats   = document.getElementById('resultStats');
    var downloadButtons    = document.getElementById('downloadButtons');
    var contextRulesNotice = document.getElementById('contextRulesNotice');
    var errorPanel    = document.getElementById('errorPanel');
    var errorMessage  = document.getElementById('errorMessage');

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        if (!fileInput.files[0]) {
            alert('Please select a file first.');
            return;
        }

        submitBtn.disabled = true;
        submitLabel.textContent = 'Processing\u2026';
        submitSpinner.classList.remove('hidden');
        resultsPanel.classList.add('hidden');
        errorPanel.classList.add('hidden');

        var formData = new FormData(form);

        fetch('{{ route("datasets.upload") }}', {
            method:  'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body:    formData,
        })
        .then(function (r) { return r.json(); })
        .then(function (json) {
            if (json.status === 'success') {
                showResults(json);
            } else {
                showError(json.message || 'An unexpected error occurred.');
            }
        })
        .catch(function (err) {
            showError('Network error: ' + err.message);
        })
        .finally(function () {
            submitBtn.disabled = false;
            submitLabel.textContent = 'Process file';
            submitSpinner.classList.add('hidden');
        });
    });

    function statChip(label, value, colour) {
        var map = { gray: 'bg-gray-100 text-gray-700', blue: 'bg-blue-50 text-blue-700', green: 'bg-green-50 text-green-700', amber: 'bg-amber-50 text-amber-700' };
        return '<div class="rounded-lg px-3 py-2 ' + (map[colour] || map.gray) + '"><p class="text-lg font-bold">' + value + '</p><p class="text-xs">' + label + '</p></div>';
    }

    function dlBtn(label, url, style) {
        var map = { primary: 'bg-blue-600 hover:bg-blue-700 text-white', secondary: 'bg-gray-100 hover:bg-gray-200 text-gray-800', accent: 'bg-green-600 hover:bg-green-700 text-white' };
        return '<a href="' + url + '" download class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium transition-colors ' + (map[style] || map.primary) + '">' +
            '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>' +
            label + '</a>';
    }

    function showResults(json) {
        var data = json.data || {};
        var urls = json.download_urls || {};
        var chips = [];
        if (data.final_rows    != null) chips.push(statChip('Output rows',         Number(data.final_rows).toLocaleString(), 'blue'));
        if (data.final_cols    != null) chips.push(statChip('Columns',             data.final_cols,                          'gray'));
        if (data.null_remaining != null) chips.push(statChip('NULLs remaining',   data.null_remaining,                      data.null_remaining > 0 ? 'amber' : 'green'));
        if (data.dedup_after_merge > 0)  chips.push(statChip('Duplicates removed', data.dedup_after_merge,                  'amber'));
        resultStats.innerHTML = chips.join('');

        var btns = [];
        if (urls.cleaned)    btns.push(dlBtn('Download cleaned CSV',  urls.cleaned,    'primary'));
        if (urls.enriched)   btns.push(dlBtn('Download enriched CSV', urls.enriched,   'primary'));
        if (urls.report_pdf) btns.push(dlBtn('Download PDF report',   urls.report_pdf, 'accent'));
        if (urls.report)     btns.push(dlBtn('Download JSON report',  urls.report,     'secondary'));
        downloadButtons.innerHTML = btns.join('');

        contextRulesNotice.classList.toggle('hidden', !json.context_rules_applied);
        resultsPanel.classList.remove('hidden');
        resultsPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function showError(msg) {
        errorMessage.textContent = msg;
        errorPanel.classList.remove('hidden');
        errorPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

})();
</script>

</x-app-layout>