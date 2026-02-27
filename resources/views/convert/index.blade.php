<x-app-layout :title="'Convert Data'">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Convert Data
        </h2>
    </x-slot>

    <div class="max-w-6xl mx-auto p-6 space-y-6">

        {{-- Top card: Converter --}}
        <div class="bg-white rounded-2xl shadow ring-1 ring-black/5 p-6">
            <form id="convertForm" class="space-y-5" enctype="multipart/form-data">
                @csrf

                {{-- Drag & Drop zone --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Input file
                    </label>

                    {{-- Verborgen invoer voor echte bestanden (we activeren deze via een klik op de dropzone) --}}
                    <input
                        id="fileInput"
                        type="file"
                        name="file"
                        required
                        accept=".csv,.txt,.xlsx,.xls,.json,.xml"
                        class="hidden"
                    />

                    <div
                        id="dropzone"
                        class="rounded-2xl border-2 border-dashed border-gray-300 bg-gray-50
                               hover:bg-gray-100 transition p-8 cursor-pointer
                               flex flex-col items-center justify-center text-center gap-2"
                    >
                        {{-- Simpele icon --}}
                        <div class="text-gray-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M3 15.75V18a2.25 2.25 0 002.25 2.25h13.5A2.25 2.25 0 0021 18v-2.25M12 3v12m0 0l-3.75-3.75M12 15l3.75-3.75" />
                            </svg>
                        </div>

                        <div class="text-gray-700 font-semibold">
                            Drag & Drop your file here
                        </div>
                        <div class="text-sm text-gray-500">
                            or click to browse
                        </div>

                        {{-- File info (ingevuld door JS) --}}
                        <div id="fileMeta" class="mt-2 text-sm text-gray-600"></div>
                        <div id="fileSizeInfo" class="text-sm"></div>
                    </div>
                </div>

                {{-- Target select --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Convert to</label>

                    {{-- We bouwen opties dynamisch op in JavaScript --}}
                    <select id="targetSelect" name="target" class="w-full rounded-xl border px-3 py-2">
                        <option value="xlsx">XLSX</option>
                        <option value="json">JSON</option>
                        <option value="xml">XML</option>
                        <option value="txt">TXT (TSV)</option>
                        <option value="csv">CSV</option>
                    </select>
                <br>

                    <p class="mt-2 text-xs text-gray-500">
                        Tip: the target will hide the same format as your input file (e.g. JSON → JSON won’t appear).
                    </p>
                </div>
                <br>

                {{-- Actions --}}
                <div class="flex justify-center items-center gap-3">
                    <button
                        id="submitBtn"
                        type="submit"
                        class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-6 py-3
                               text-white font-semibold shadow hover:bg-indigo-700 disabled:opacity-60"
                    >
                        Convert
                    </button>

                    <div id="loading" class="hidden text-sm text-gray-600">
                        Converting... please wait.
                    </div>
                </div>
            </form>
        </div>

        {{-- Result card --}}
        <div id="resultBox" class="hidden bg-white rounded-2xl shadow ring-1 ring-black/5 p-6 space-y-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-lg font-semibold text-gray-900">Result</div>
                    <div id="summary" class="text-sm text-gray-600"></div>
                </div>

                {{-- Het download-attribuut helpt browsers, maar de serverheaders zijn de echte vlotte kracht. --}}
                <a
                    id="downloadLink"
                    href="#"
                    download
                    class="text-sm rounded-xl bg-green-600 px-4 py-2 text-white font-semibold hover:bg-green-700"
                >
                    Download
                </a>
            </div>

            <div>
                <div class="text-sm font-semibold text-gray-700 mb-2">Preview (first 5 rows)</div>
                <div class="overflow-x-auto border rounded-xl">
                    <table class="min-w-max text-sm">
                        <thead class="bg-gray-50" id="previewHead"></thead>
                        <tbody id="previewBody"></tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Error bericht --}}
        <div id="errorBox" class="hidden rounded-xl border border-red-200 bg-red-50 p-4 text-red-700"></div>
    </div>

    <script>
        //hier wordt bytes omzetten naar een leesbare tekenreeks.
        function formatBytes(bytes) {
            if (!bytes || bytes < 1) return "0 B";
            const units = ["B", "KB", "MB", "GB"];
            const i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
            const value = bytes / Math.pow(1024, i);
            return `${value.toFixed(i === 0 ? 0 : 2)} ${units[i]}`;
        }

        // Extraheer de bestandsextensie uit de bestandsnaam in lowercase.
        function getExt(filename) {
            const parts = (filename || '').split('.');
            return (parts.length > 1 ? parts.pop() : '').toLowerCase();
        }

        // variablele
        const form = document.getElementById('convertForm');
        const submitBtn = document.getElementById('submitBtn');
        const loading = document.getElementById('loading');

        const resultBox = document.getElementById('resultBox');
        const errorBox = document.getElementById('errorBox');
        const downloadLink = document.getElementById('downloadLink');
        const summary = document.getElementById('summary');

        const previewHead = document.getElementById('previewHead');
        const previewBody = document.getElementById('previewBody');

        const dropzone = document.getElementById('dropzone');
        const fileInput = document.getElementById('fileInput');
        const fileMeta = document.getElementById('fileMeta');
        const fileSizeInfo = document.getElementById('fileSizeInfo');

        const targetSelect = document.getElementById('targetSelect');

        // Limiteit van de bestand
        const MAX_MB = 20;
        const MAX_BYTES = MAX_MB * 1024 * 1024;

        // Alle mogelijke targets (we filteren het doel eruit dat overeenkomt met de invoerextensie).
        const ALL_TARGETS = [
            { value: 'xlsx', label: 'XLSX' },
            { value: 'json', label: 'JSON' },
            { value: 'xml',  label: 'XML'  },
            { value: 'txt',  label: 'TXT (TSV)' },
            { value: 'csv',  label: 'CSV' },
        ];

        // hier gaat het doeldropdownmenu herbouwen zodat het niet dezelfde opmaak weergeeft als het invoerbestand.
        // vb: input = json => doet "JSON" optie weg.
        function rebuildTargetOptions(inputExt) {
            const ext = (inputExt || '').toLowerCase();
            const previousValue = targetSelect.value;

            // Clear existing options
            targetSelect.innerHTML = '';

            // Voeg alle opties toe, behalve de optie die gelijk is aan de invoer ext
            // NOTE: Voor XLS (legacy) behouden we nog steeds XLSX (dus ext === "xls" verwijdert xlsx niet).
            ALL_TARGETS.forEach(opt => {
                if (opt.value === ext) return; // hetzelfde type verbergen
                const o = document.createElement('option');
                o.value = opt.value;
                o.textContent = opt.label;
                targetSelect.appendChild(o);
            });

            // hier wordt geprobeerd de vorige selectie te behouden als deze nog bestaat.
            const stillExists = Array.from(targetSelect.options).some(o => o.value === previousValue);
            if (stillExists) {
                targetSelect.value = previousValue;
            } else if (targetSelect.options.length) {
                // else pick first available option
                targetSelect.value = targetSelect.options[0].value;
            }
        }

        // UI-labels bijwerken na bestandsselectie + grootte valideren.
        function handleFileSelected() {
            const f = fileInput.files?.[0];
            if (!f) {
                fileMeta.textContent = '';
                fileSizeInfo.textContent = '';
                fileSizeInfo.className = 'text-sm text-gray-600';
                // als er geen bestanden zijn, gaat alle targes tonnen (default)
                rebuildTargetOptions('');
                return;
            }

            // toon bestands naam
            fileMeta.textContent = `Selected: ${f.name}`;

            // Valideer de bestandsgrootte (front-end)
            const ok = f.size <= MAX_BYTES;
            fileSizeInfo.textContent = `File size: ${formatBytes(f.size)} ${ok ? `(OK)` : `(Too large, max ${MAX_MB}MB)`}`;
            fileSizeInfo.className = 'text-sm ' + (ok ? 'text-green-700' : 'text-red-700');

            // Filter target dropdown gebaseerd op invoeruitbreiding
            const ext = getExt(f.name);
            rebuildTargetOptions(ext);

            // Verzenden uitschakelen als het te groot is
            submitBtn.disabled = !ok;
        }

        // Dropzone opklikken => open de file picker.
        dropzone.addEventListener('click', () => fileInput.click());

        // Sleep gebeurtenissen naar de stijl-dropzone.
        dropzone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropzone.classList.add('ring-2', 'ring-blue-400');
        });

        dropzone.addEventListener('dragleave', () => {
            dropzone.classList.remove('ring-2', 'ring-blue-400');
        });

        // Op drop: Kopieert de bestanden naar het verborgen invoerveld en verwerk vervolgens de UI-update.
        dropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropzone.classList.remove('ring-2', 'ring-blue-400');

            const files = e.dataTransfer?.files;
            if (!files || !files.length) return;

            // Sommige browsers hebben DataTransfer nodig om bestanden programmatisch toe te wijzen.
            const dt = new DataTransfer();
            for (const f of files) dt.items.add(f);
            fileInput.files = dt.files;

            handleFileSelected();
        });

        // Normale bestandsinvoerwijziging.
        fileInput.addEventListener('change', handleFileSelected);




        // Submit => AJAX POST naar Laravel route, dan show preview + download link.
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            // Reset UI states
            errorBox.classList.add('hidden');
            resultBox.classList.add('hidden');

            // Schakel de knop uit tijdens het converteren.
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

                //We verwachten JSON, maar als er iets misgaat, leest dan de tekst.
                const contentType = res.headers.get('content-type') || '';
                const isJson = contentType.includes('application/json');
                const data = isJson ? await res.json() : { success: false, message: await res.text() };

                // hier wordt de validatie- of conversiefouten behandelen aan de backend.
                if (!res.ok || !data.success) {
                    throw new Error(data.message || data.error || 'Convert failed');
                }

                // Downloadlink instellen (server zit download van bijlage af)
                downloadLink.href = data.download_url;
                downloadLink.setAttribute('download', data.summary?.file || 'download');

                // toon summary
                summary.textContent = `Rows: ${data.summary.rows} | Columns: ${data.summary.columns} | File: ${data.summary.file}`;

                // Bouw een voorbeeldtabel
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

                // toon result section
                resultBox.classList.remove('hidden');

            } catch (err) {
                // toon user-friendly error box
                errorBox.textContent = err.message;
                errorBox.classList.remove('hidden');
            } finally {
                // Opnieuw de knop inschakelen
                submitBtn.disabled = false;
                loading.classList.add('hidden');
            }
        });

        // Stel standaardopties in bij de first load
        rebuildTargetOptions('');
    </script>
</x-app-layout>
