{{-- resources/views/convert/index.blade.php --}}
<x-app-layout :title="'Convert'">
    <x-slot name="header">
        <div class="space-y-1">
            <h2 class="font-semibold text-2xl text-gray-900 leading-tight">Convert CSV</h2>
            <p class="text-sm text-gray-500">Upload CSV en kies output-formaat. Resultaat verschijnt hieronder.</p>
        </div>
    </x-slot>

    <div class="max-w-4xl mx-auto p-6 space-y-6">

        <div class="bg-white rounded-2xl shadow ring-1 ring-black/5 p-6">
            <form id="convertForm" class="space-y-4" enctype="multipart/form-data">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">CSV bestand</label>

                    <input
                        id="fileInput"
                        type="file"
                        name="file"
                        required
                        accept=".csv,.txt"
                        class="block w-full text-sm text-gray-700 file:mr-4 file:rounded-xl file:border-0
                               file:bg-blue-600 file:px-5 file:py-3 file:text-white file:font-semibold hover:file:bg-blue-700"
                    />

                    {{-- File size feedback --}}
                    <div id="fileSizeInfo" class="mt-2 text-sm text-gray-600"></div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Convert naar</label>
                    <select name="target" class="w-full rounded-xl border px-3 py-2">
                        <option value="xlsx">XLSX</option>
                        <option value="json">JSON</option>
                        <option value="xml">XML</option>
                        <option value="txt">TXT (TSV)</option>
                    </select>
                </div>

                <div class="flex items-center gap-3">
                    <button id="submitBtn" type="submit"
                            class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-6 py-3 text-white font-semibold shadow hover:bg-blue-700">
                        Convert
                    </button>

                    <div id="loading" class="hidden text-sm text-gray-600">
                        Converting... even geduld.
                    </div>
                </div>
            </form>
        </div>

        <div id="resultBox" class="hidden bg-white rounded-2xl shadow ring-1 ring-black/5 p-6 space-y-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-lg font-semibold text-gray-900">Resultaat</div>
                    <div id="summary" class="text-sm text-gray-600"></div>
                </div>

                <a id="downloadLink" href="#"
                   class="text-sm rounded-xl bg-green-600 px-4 py-2 text-white font-semibold hover:bg-green-700">
                    Download
                </a>
            </div>

            <div>
                <div class="text-sm font-semibold text-gray-700 mb-2">Preview (eerste 5 rijen)</div>

                {{-- Belangrijk: horizontaal scrollen zodat je tabel niet “naar rechts” uitrekt --}}
                <div class="overflow-x-auto border rounded-xl">
                    <table class="min-w-max text-sm">
                        <thead class="bg-gray-50" id="previewHead"></thead>
                        <tbody id="previewBody"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="errorBox" class="hidden rounded-xl border border-red-200 bg-red-50 p-4 text-red-700"></div>
    </div>

    <script>
        // --- helpers ---
        function formatBytes(bytes) {
            if (!bytes || bytes < 1) return "0 B";
            const units = ["B", "KB", "MB", "GB"];
            const i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
            const value = bytes / Math.pow(1024, i);
            return `${value.toFixed(i === 0 ? 0 : 2)} ${units[i]}`;
        }

        // --- elements ---
        const form = document.getElementById('convertForm');
        const submitBtn = document.getElementById('submitBtn');
        const loading = document.getElementById('loading');

        const resultBox = document.getElementById('resultBox');
        const errorBox = document.getElementById('errorBox');
        const downloadLink = document.getElementById('downloadLink');
        const summary = document.getElementById('summary');

        const previewHead = document.getElementById('previewHead');
        const previewBody = document.getElementById('previewBody');

        const fileInput = document.getElementById('fileInput');
        const fileSizeInfo = document.getElementById('fileSizeInfo');

        // (optioneel) max 20MB label (frontend-only)
        const MAX_MB = 20;
        const MAX_BYTES = MAX_MB * 1024 * 1024;

        fileInput.addEventListener('change', () => {
            const f = fileInput.files?.[0];
            if (!f) {
                fileSizeInfo.textContent = '';
                fileSizeInfo.className = 'mt-2 text-sm text-gray-600';
                return;
            }

            const ok = f.size <= MAX_BYTES;
            fileSizeInfo.textContent = `File size: ${formatBytes(f.size)} ${ok ? `(OK)` : `(Too large, max ${MAX_MB}MB)`}`;
            fileSizeInfo.className = 'mt-2 text-sm ' + (ok ? 'text-green-700' : 'text-red-700');
        });

        // --- submit ---
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            errorBox.classList.add('hidden');
            resultBox.classList.add('hidden');

            submitBtn.disabled = true;
            loading.classList.remove('hidden');

            const fd = new FormData(form);

            try {
                const res = await fetch("{{ route('convert.convert') }}", {
                    method: 'POST',
                    body: fd,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                    }
                });

                // als server HTML error terugstuurt (bv. PostTooLarge), dit voorkomt JSON.parse crash
                const contentType = res.headers.get('content-type') || '';
                const isJson = contentType.includes('application/json');
                const data = isJson ? await res.json() : { success: false, message: await res.text() };

                if (!res.ok || !data.success) {
                    throw new Error(data.message || data.error || 'Convert failed');
                }

                downloadLink.href = data.download_url;
                summary.textContent = `Rows: ${data.summary.rows} | Columns: ${data.summary.columns} | File: ${data.summary.file}`;

                // preview table
                previewHead.innerHTML = '';
                previewBody.innerHTML = '';

                const headers = data.preview.headers || [];
                const rows = data.preview.rows || [];

                const trh = document.createElement('tr');
                headers.forEach(h => {
                    const th = document.createElement('th');
                    th.className = 'text-left px-3 py-2 font-semibold text-gray-700 border-b whitespace-nowrap';
                    th.textContent = h;
                    trh.appendChild(th);
                });
                previewHead.appendChild(trh);

                rows.forEach(r => {
                    const tr = document.createElement('tr');
                    headers.forEach(h => {
                        const td = document.createElement('td');
                        td.className = 'px-3 py-2 border-b text-gray-700 whitespace-nowrap';
                        td.textContent = (r[h] ?? '');
                        tr.appendChild(td);
                    });
                    previewBody.appendChild(tr);
                });

                resultBox.classList.remove('hidden');

            } catch (err) {
                errorBox.textContent = err.message;
                errorBox.classList.remove('hidden');
            } finally {
                submitBtn.disabled = false;
                loading.classList.add('hidden');
            }
        });
    </script>
</x-app-layout>
