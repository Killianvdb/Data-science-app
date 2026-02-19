<x-app-layout :title="'Upload Dataset'">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Upload & Clean Dataset') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 space-y-6">

                <div>
                    <h1 class="text-2xl font-bold text-gray-800 mb-1">Data Cleaning Tool</h1>
                    <p class="text-gray-600">Upload files to clean and convert to CSV format using AI-driven imputation.</p>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <p class="text-sm text-blue-800">
                        <strong>Supported formats:</strong> CSV, XLSX, XLS, XML, JSON, TXT
                    </p>
                </div>

                <form id="uploadForm" enctype="multipart/form-data" class="space-y-6">
                    @csrf

                    <div>
                        <label class="block font-medium text-gray-700 mb-2">Upload Dataset Files</label>

                        <div id="dropZone"
                            class="flex flex-col items-center justify-center w-full p-8 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100 transition">

                            <svg class="w-10 h-10 text-gray-400 mb-3" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3 16.5V17a2 2 0 002 2h14a2 2 0 002-2v-.5M7.5 12L12 7.5M12 7.5L16.5 12M12 7.5V21" />
                            </svg>

                            <p class="text-gray-600 font-medium">
                                Drag & Drop your files here
                            </p>
                            <p class="text-sm text-gray-400">
                                or click to browse
                            </p>

                            <input
                                type="file"
                                name="files[]"
                                id="fileInput"
                                multiple
                                class="hidden"
                            >
                        </div>

                        <div id="fileList" class="mt-4 space-y-2 text-sm text-gray-600"></div>

                    </div>

                    <div class="border-t pt-6">
                        <h3 class="text-lg font-semibold text-gray-700 mb-4">Advanced Options (Optional)</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Row Threshold (0-1)</label>
                                <input type="number" name="row_threshold" step="0.1" min="0" max="1" placeholder="0.8"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Column Threshold (0-1)</label>
                                <input type="number" name="col_threshold" step="0.1" min="0" max="1" placeholder="0.8"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>

                            <div class="md:col-span-2 bg-gray-50 p-4 rounded-lg border border-gray-200">
                                <div class="flex justify-between items-center mb-2">
                                    <label class="block text-sm font-medium text-gray-700">
                                        Custom Special Characters
                                    </label>
                                    <button type="button" onclick="toggleCharList()" class="text-xs text-indigo-600 hover:underline">
                                        View Default List
                                    </button>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <input type="text" name="special_characters" placeholder="e.g. $,?"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <p class="text-xs text-gray-500 mt-1">Comma separated list of chars</p>
                                    </div>

                                    <div>
                                        <select name="action" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <option value="">None (Use Default List)</option>
                                            <option value="add">Add these to the Default List</option>
                                            <option value="remove">Remove these from the Default List</option>
                                        </select>
                                    </div>
                                </div>

                                <div id="defaultCharList" class="hidden mt-3 p-3 bg-white border rounded text-xs text-gray-600 break-all font-mono">
                                    <strong>Default character set:</strong><br>
                                    !, ", #, %, &, ', (, ), *, +, ,, -, ., /, :, ;, <, =, >, ?, @, [, \, ], ^, _, `, {, |, }, ~, –, //, %*, :/, .;, Ø, §, $, £
                                </div>
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Missing Value Imputation Method</label>
                                <select name="imputation_type" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="RDF">Random Forest (RDF) - Best for complex data</option>
                                    <option value="KNN">K-Nearest Neighbors (KNN)</option>
                                    <option value="mean">Mean (Numerical Only)</option>
                                    <option value="median">Median (Numerical Only)</option>
                                    <option value="most_frequent">Most Frequent (Categorical)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-4">
                        <button type="submit" id="submitBtn" class="inline-flex items-center px-6 py-3 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition duration-150">
                            Start Cleaning Process
                        </button>
                        <a href="{{ route('datasets.files') }}" class="text-sm text-gray-600 hover:text-gray-900 underline">
                            View My Datasets
                        </a>
                    </div>
                </form>

                <div id="progressDiv" class="hidden bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <div class="flex items-center">
                        <svg class="animate-spin h-5 w-5 mr-3 text-yellow-600" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <span class="text-yellow-800 font-medium">Running Python scripts... this may take a moment.</span>
                    </div>
                </div>

                <div id="resultDiv" class="hidden"></div>
            </div>
        </div>
    </div>


    <script>
        function toggleCharList() {
            const list = document.getElementById('defaultCharList');
            list.classList.toggle('hidden');
        }

        document.getElementById('uploadForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const submitBtn = document.getElementById('submitBtn');
            const progressDiv = document.getElementById('progressDiv');
            const resultDiv = document.getElementById('resultDiv');
            const fileInput = document.getElementById('fileInput');

            progressDiv.classList.remove('hidden');
            resultDiv.classList.add('hidden');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Processing...';

            const formData = new FormData(this);

            formData.delete('files');
            formData.delete('files[]');

            if (!dt.files.length) {
                progressDiv.classList.add('hidden');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Start Cleaning Process';
                resultDiv.classList.remove('hidden');
                resultDiv.innerHTML = `<div class="bg-red-50 p-6 rounded-lg text-red-700"><strong>Error:</strong> Please add at least 1 file.</div>`;
                return;
            }

            Array.from(dt.files).forEach(file => {
                formData.append('files[]', file);
            });

            try {
                const response = await fetch('{{ route("datasets.batchUpload") }}', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                    }
                });


                //const resultText = await response.text();
                /*
                resultDiv.innerHTML = `<pre class="bg-gray-100 p-4 rounded overflow-auto text-xs">${resultText}</pre>`;
                progressDiv.classList.add('hidden');
                resultDiv.classList.remove('hidden');
                return;
                */

                progressDiv.classList.add('hidden');
                resultDiv.classList.remove('hidden');

                const raw = await response.text();
                let result;

                try {
                    result = JSON.parse(raw);
                } catch (e) {
                    throw new Error(`HTTP ${response.status}: ${raw.substring(0, 200)}`);
                }


                if (!response.ok) {

                    progressDiv.classList.add('hidden');
                    resultDiv.classList.remove('hidden');

                    const msg = result.message || 'Request failed';

                    if (response.status === 403) {

                        const isPro = userPlan === 'pro';

                        const actionButton = isPro
                        ? `
                            <a href="{{ route('contact') }}"
                            class="inline-block bg-gray-900 hover:bg-gray-800 text-white font-semibold py-2 px-5 rounded-lg transition">
                            Contact Us
                            </a>
                        `
                        : `
                            <a href="{{ route('pricing') }}"
                            class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-5 rounded-lg transition">
                            Upgrade Plan
                            </a>
                        `;

                        resultDiv.innerHTML = `
                            <div class="bg-red-50 p-6 rounded-lg text-red-700">
                                <strong>Error:</strong> ${msg}
                                <div class="mt-4 flex justify-center">
                                    ${actionButton}
                                </div>
                            </div>
                        `;
                    } else {
                        resultDiv.innerHTML = `<div class="bg-red-50 p-6 rounded-lg text-red-700"><strong>Error:</strong> ${msg}</div>`;
                    }

                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Start Cleaning Process';
                    return;
                }

                const okCount = (result.results || []).filter(r => r.status === 'success').length;
                const errCount = (result.results || []).filter(r => r.status === 'error').length;

                resultDiv.innerHTML = `
                    <div class="bg-green-50 border border-green-200 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-green-800 mb-2">Upload Completed!</h3>
                        <p class="text-green-700 mb-4">${result.message || 'Operation completed'}</p>
                        <div class="bg-white rounded p-4 text-sm shadow-sm border border-green-100">
                            <p><strong>Success:</strong> ${okCount} | <strong>Errors:</strong> ${errCount}</p>
                        </div>
                        <div class="mt-4">
                            <a href="{{ route('datasets.files') }}"
                            class="inline-block bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-5 rounded-lg transition">
                                View My Datasets
                            </a>
                        </div>
                    </div>
                `;

                this.reset();
                dt = new DataTransfer();
                fileInput.files = dt.files;
                updateFileList();

            } catch (error) {
                progressDiv.classList.add('hidden');
                resultDiv.classList.remove('hidden');
                resultDiv.innerHTML = `<div class="p-6 bg-red-50 text-red-700 rounded-lg"><strong>Error:</strong> ${error.message}</div>`;
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Start Cleaning Process';
            }
        });


        //DROP ZONE:

        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        const fileList = document.getElementById('fileList');

        const userPlan = "{{ $planSlug }}";

        let dt = new DataTransfer();

        dropZone.addEventListener('click', () => fileInput.click());

        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('border-indigo-500', 'bg-indigo-50');
        });

        dropZone.addEventListener('dragleave', () => {

            dropZone.classList.remove('border-indigo-500', 'bg-indigo-50');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('border-indigo-500', 'bg-indigo-50');

            addFiles(e.dataTransfer.files);

        });

        fileInput.addEventListener('change', () => {
            addFiles(fileInput.files);

        });

        function addFiles(fileListToAdd) {
            const existingKeys = new Set(Array.from(dt.files).map(fileKey));

            Array.from(fileListToAdd).forEach(file => {
                const key = fileKey(file);
                if (!existingKeys.has(key)) {
                    dt.items.add(file);
                    existingKeys.add(key);
                }
            });

            fileInput.files = dt.files;
            updateFileList();
        }

        function fileKey(file) {

            // WE WANT TO AVOID DUPLICATES

            return `${file.name}__${file.size}__${file.lastModified}`;
        }

        function updateFileList() {
            fileList.innerHTML = '';

            if (!dt.files.length) {
                return;
            }

            Array.from(dt.files).forEach((file, index) => {
                const div = document.createElement('div');
                div.className = "flex justify-between items-center bg-gray-100 px-3 py-2 rounded";

                div.innerHTML = `
                    <span>${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)</span>
                    <button type="button" class="text-red-500 hover:text-red-700 text-xs"
                        onclick="removeFile(${index})">
                        Remove
                    </button>
                `;
                fileList.appendChild(div);
            });
        }

        window.removeFile = function(index) {
            const newDt = new DataTransfer();
            const files = Array.from(dt.files);

            files.forEach((file, i) => {
                if (i !== index) newDt.items.add(file);
            });

            dt = newDt;
            fileInput.files = dt.files;
            updateFileList();
        }


    </script>
    {{-- @endpush --}}
</x-app-layout>
