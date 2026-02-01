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
                        <label class="block font-medium text-gray-700 mb-2">Select Dataset File</label>
                        <input 
                            type="file" 
                            name="file" 
                            id="fileInput"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            required
                        >
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
                        <a href="{{ route('datasets.index') }}" class="text-sm text-gray-600 hover:text-gray-900 underline">
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
            
            try {
                const response = await fetch('{{ route("datasets.upload") }}', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                    }
                });
                
                const result = await response.json();
                progressDiv.classList.add('hidden');
                resultDiv.classList.remove('hidden');
                
                if (result.status === 'success') {
                    const originalFileName = fileInput.files[0].name.split('.')[0];
                    const prettyDownloadUrl = `${result.download_url}/${originalFileName}_CLEANED.csv`;

                    resultDiv.innerHTML = `
                        <div class="bg-green-50 border border-green-200 rounded-lg p-6">
                            <h3 class="text-lg font-semibold text-green-800 mb-2">Cleaning Complete!</h3>
                            <p class="text-green-700 mb-4">${result.message}</p>
                            <div class="bg-white rounded p-4 mb-4 text-sm shadow-sm border border-green-100">
                                <p><strong>Final Rows:</strong> ${result.data.rows} | <strong>Final Columns:</strong> ${result.data.columns}</p>
                            </div>
                            <a href="${prettyDownloadUrl}" class="inline-block bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-6 rounded-lg transition">
                                Download Cleaned CSV
                            </a>
                        </div>
                    `;
                    this.reset();
                } else {
                    resultDiv.innerHTML = `<div class="bg-red-50 p-6 rounded-lg text-red-700"><strong>Error:</strong> ${result.message}</div>`;
                }
            } catch (error) {
                progressDiv.classList.add('hidden');
                resultDiv.classList.remove('hidden');
                resultDiv.innerHTML = `<div class="p-6 bg-red-50 text-red-700 rounded-lg">Server Error: Could not reach the script.</div>`;
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Start Cleaning Process';
            }
        });
    </script>
    {{-- @endpush --}}
</x-app-layout>