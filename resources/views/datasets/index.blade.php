<x-app-layout :title="'Upload Dataset'">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Upload & Clean Dataset') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 space-y-6">

                <div>
                    <h1 class="text-2xl font-bold text-gray-800 mb-1">AI-Powered Data Cleaning & Enrichment</h1>
                    <p class="text-gray-600">Upload your dataset with optional reference files for automatic cross-referencing and enrichment.</p>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <p class="text-sm text-blue-800">
                        <strong>Supported formats:</strong> CSV, XLSX, XLS, XML, JSON, TXT
                    </p>
                </div>

                <!-- Pipeline Mode Selection -->
                <div class="bg-gradient-to-r from-indigo-50 to-purple-50 border border-indigo-200 rounded-lg p-5">
                    <label class="block font-semibold text-gray-800 mb-3">Processing Mode</label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <label class="flex items-start p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-indigo-400 transition bg-white">
                            <input type="radio" name="pipeline_mode" value="clean_only" checked class="mt-1 mr-3">
                            <div>
                                <p class="font-semibold text-gray-800">🧹 Clean Only</p>
                                <p class="text-sm text-gray-600 mt-1">Basic cleaning: remove duplicates, handle missing values, fix formats</p>
                            </div>
                        </label>
                        <label class="flex items-start p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-indigo-400 transition bg-white">
                            <input type="radio" name="pipeline_mode" value="full_pipeline" class="mt-1 mr-3">
                            <div>
                                <p class="font-semibold text-gray-800">🚀 Full Pipeline</p>
                                <p class="text-sm text-gray-600 mt-1">Clean + cross-reference + validation + AI enrichment</p>
                            </div>
                        </label>
                    </div>
                </div>

                <form id="uploadForm" enctype="multipart/form-data" class="space-y-6">
                    @csrf

                    <!-- Main File Upload -->
                    <div>
                        <label class="block font-medium text-gray-700 mb-2">📁 Main Dataset File <span class="text-red-500">*</span></label>

                        <div id="dropZone"
                            class="flex flex-col items-center justify-center w-full p-8 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100 transition">

                            <svg class="w-10 h-10 text-gray-400 mb-3" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3 16.5V17a2 2 0 002 2h14a2 2 0 002-2v-.5M7.5 12L12 7.5M12 7.5L16.5 12M12 7.5V21" />
                            </svg>

                            <p class="text-gray-600 font-medium">Drag & Drop your main file here</p>
                            <p class="text-sm text-gray-400">or click to browse</p>

                            <input type="file" name="file" id="mainFileInput" class="hidden" accept=".csv,.xlsx,.xls,.json,.xml,.txt">
                        </div>

                        <div id="mainFileDisplay" class="mt-3 text-sm text-gray-600"></div>
                    </div>

                    <!-- Reference Files (for Full Pipeline) -->
                    <div id="referenceFilesSection" class="hidden">
                        <label class="block font-medium text-gray-700 mb-2">
                            📊 Reference Files <span class="text-gray-500">(Optional)</span>
                        </label>
                        <p class="text-sm text-gray-600 mb-3">Upload additional files to enrich your main dataset (e.g., product catalog, customer database)</p>

                        <div id="refDropZone"
                            class="flex flex-col items-center justify-center w-full p-6 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100 transition">
                            <svg class="w-8 h-8 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            <p class="text-gray-600 text-sm">Add reference files</p>
                            <input type="file" name="reference_files[]" id="refFileInput" multiple class="hidden" accept=".csv,.xlsx,.xls">
                        </div>

                        <div id="refFileList" class="mt-3 space-y-2"></div>
                    </div>

                    <!-- AI Options (for Full Pipeline) -->
                    <div id="aiOptionsSection" class="hidden border-t pt-6">
                        <h3 class="text-lg font-semibold text-gray-700 mb-4">🤖 AI Enrichment Options</h3>

                        <div class="space-y-4">
                            <!-- LLM Enricher Toggle -->
                            <label class="flex items-center p-4 bg-indigo-50 border border-indigo-200 rounded-lg cursor-pointer">
                                <input type="checkbox" name="use_llm_enricher" value="1" checked class="mr-3 h-5 w-5 text-indigo-600">
                                <div>
                                    <p class="font-semibold text-gray-800">Enable AI-Powered Enrichment</p>
                                    <p class="text-sm text-gray-600">Use LLM to intelligently fill missing values based on context</p>
                                </div>
                            </label>

                            <!-- Rules File Upload -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    ⚙️ Custom Validation Rules <span class="text-gray-500">(Optional JSON file)</span>
                                </label>
                                <input type="file" name="rules_file" id="rulesFileInput" accept=".json"
                                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200">
                                <p class="text-xs text-gray-500 mt-1">Upload a JSON file with custom validation rules</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-4">
                        <button type="submit" id="submitBtn" class="inline-flex items-center px-6 py-3 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition duration-150">
                            🚀 Start Processing
                        </button>
                        <a href="{{ route('datasets.files') }}" class="text-sm text-gray-600 hover:text-gray-900 underline">
                            View My Datasets →
                        </a>
                    </div>
                </form>

                <!-- Progress Indicator -->
                <div id="progressDiv" class="hidden bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <div class="flex items-center">
                        <svg class="animate-spin h-5 w-5 mr-3 text-yellow-600" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <div>
                            <p class="text-yellow-800 font-medium">Processing your data...</p>
                            <p class="text-sm text-yellow-700">This may take a few moments depending on file size</p>
                        </div>
                    </div>
                </div>

                <!-- Result Display -->
                <div id="resultDiv" class="hidden"></div>
            </div>
        </div>
    </div>

    <script>
        // Pipeline mode toggle
        const pipelineModeRadios = document.querySelectorAll('input[name="pipeline_mode"]');
        const referenceFilesSection = document.getElementById('referenceFilesSection');
        const aiOptionsSection = document.getElementById('aiOptionsSection');

        pipelineModeRadios.forEach(radio => {
            radio.addEventListener('change', (e) => {
                if (e.target.value === 'full_pipeline') {
                    referenceFilesSection.classList.remove('hidden');
                    aiOptionsSection.classList.remove('hidden');
                } else {
                    referenceFilesSection.classList.add('hidden');
                    aiOptionsSection.classList.add('hidden');
                }
            });
        });

        // Main file upload
        const dropZone = document.getElementById('dropZone');
        const mainFileInput = document.getElementById('mainFileInput');
        const mainFileDisplay = document.getElementById('mainFileDisplay');

        dropZone.addEventListener('click', () => mainFileInput.click());
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
            if (e.dataTransfer.files.length > 0) {
                mainFileInput.files = e.dataTransfer.files;
                updateMainFileDisplay();
            }
        });

        mainFileInput.addEventListener('change', updateMainFileDisplay);

        function updateMainFileDisplay() {
            if (mainFileInput.files.length > 0) {
                const file = mainFileInput.files[0];
                mainFileDisplay.innerHTML = `
                    <div class="flex justify-between items-center bg-green-50 px-4 py-3 rounded-lg border border-green-200">
                        <span class="text-green-800">✓ ${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)</span>
                        <button type="button" onclick="clearMainFile()" class="text-red-500 hover:text-red-700 text-sm font-medium">Remove</button>
                    </div>
                `;
            } else {
                mainFileDisplay.innerHTML = '';
            }
        }

        window.clearMainFile = function() {
            mainFileInput.value = '';
            updateMainFileDisplay();
        }

        // Reference files upload
        const refDropZone = document.getElementById('refDropZone');
        const refFileInput = document.getElementById('refFileInput');
        const refFileList = document.getElementById('refFileList');
        let refDt = new DataTransfer();

        refDropZone.addEventListener('click', () => refFileInput.click());
        refDropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            refDropZone.classList.add('border-indigo-500', 'bg-indigo-50');
        });
        refDropZone.addEventListener('dragleave', () => {
            refDropZone.classList.remove('border-indigo-500', 'bg-indigo-50');
        });
        refDropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            refDropZone.classList.remove('border-indigo-500', 'bg-indigo-50');
            addRefFiles(e.dataTransfer.files);
        });

        refFileInput.addEventListener('change', () => {
            addRefFiles(refFileInput.files);
        });

        function addRefFiles(files) {
            Array.from(files).forEach(file => {
                refDt.items.add(file);
            });
            refFileInput.files = refDt.files;
            updateRefFileList();
        }

        function updateRefFileList() {
            refFileList.innerHTML = '';
            if (refDt.files.length === 0) return;

            Array.from(refDt.files).forEach((file, index) => {
                const div = document.createElement('div');
                div.className = "flex justify-between items-center bg-gray-100 px-3 py-2 rounded";
                div.innerHTML = `
                    <span class="text-sm">${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)</span>
                    <button type="button" class="text-red-500 hover:text-red-700 text-xs" onclick="removeRefFile(${index})">Remove</button>
                `;
                refFileList.appendChild(div);
            });
        }

        window.removeRefFile = function(index) {
            const newDt = new DataTransfer();
            Array.from(refDt.files).forEach((file, i) => {
                if (i !== index) newDt.items.add(file);
            });
            refDt = newDt;
            refFileInput.files = refDt.files;
            updateRefFileList();
        }

        // Form submission
        document.getElementById('uploadForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const submitBtn = document.getElementById('submitBtn');
            const progressDiv = document.getElementById('progressDiv');
            const resultDiv = document.getElementById('resultDiv');

            // Validation
            if (!mainFileInput.files.length) {
                alert('Please select a main file');
                return;
            }

            progressDiv.classList.remove('hidden');
            resultDiv.classList.add('hidden');
            submitBtn.disabled = true;
            submitBtn.textContent = '⏳ Processing...';

            const formData = new FormData(this);

            try {
                const response = await fetch('{{ route("datasets.upload") }}', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                    }
                });

                progressDiv.classList.add('hidden');
                resultDiv.classList.remove('hidden');

                const result = await response.json();

                if (!response.ok) {
                    throw new Error(result.message || 'Processing failed');
                }

                // Success display
                let downloadsHtml = '';
                if (result.download_urls) {
                    if (result.download_urls.cleaned) {
                        downloadsHtml += `<a href="${result.download_urls.cleaned}" class="inline-block bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded-lg transition mr-2 mb-2">📥 Download Cleaned File</a>`;
                    }
                    if (result.download_urls.enriched) {
                        downloadsHtml += `<a href="${result.download_urls.enriched}" class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg transition mr-2 mb-2">📥 Download Enriched File</a>`;
                    }
                    if (result.download_urls.report) {
                        downloadsHtml += `<a href="${result.download_urls.report}" class="inline-block bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-4 rounded-lg transition mr-2 mb-2">📄 Download Report (JSON)</a>`;
                    }
                }

                let visualiseHtml = '';
                if (result.visualise_url) {
                    visualiseHtml += `
                        <a href="${result.visualise_url}"
                        class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition mr-2 mb-2">
                        📊 Visualise Cleaned File
                        </a>`;
                }

                if (result.visualise_enriched_url) {
                    visualiseHtml += `
                        <a href="${result.visualise_enriched_url}"
                        class="inline-block bg-purple-600 hover:bg-purple-700 text-white font-semibold py-2 px-4 rounded-lg transition mr-2 mb-2">
                        📈 Visualise Enriched File
                        </a>`;
                }

                resultDiv.innerHTML = `
                    <div class="bg-green-50 border border-green-200 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-green-800 mb-2">✅ Processing Completed!</h3>
                        <p class="text-green-700 mb-4">${result.message}</p>
                        <div class="flex flex-wrap gap-2 mb-4">
                            ${downloadsHtml}
                            ${visualiseHtml}
                        </div>
                        <a href="{{ route('datasets.files') }}" class="inline-block text-sm text-gray-600 hover:text-gray-900 underline">View all my datasets →</a>
                    </div>
                `;

                // Reset form
                this.reset();
                mainFileDisplay.innerHTML = '';
                refDt = new DataTransfer();
                refFileInput.files = refDt.files;
                updateRefFileList();

            } catch (error) {
                progressDiv.classList.add('hidden');
                resultDiv.classList.remove('hidden');
                resultDiv.innerHTML = `
                    <div class="bg-red-50 border border-red-200 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-red-800 mb-2">❌ Error</h3>
                        <p class="text-red-700">${error.message}</p>
                    </div>
                `;
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = '🚀 Start Processing';
            }
        });
    </script>
</x-app-layout>
