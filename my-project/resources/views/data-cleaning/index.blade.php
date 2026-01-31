<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Data Cleaning Tool</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Data Cleaning & Conversion Tool</h1>
            <p class="text-gray-600 mb-8">Upload files to clean and convert to CSV format using AI-driven imputation</p>

            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <p class="text-sm text-blue-800">
                    <strong>Supported formats:</strong> 
                    {{ implode(', ', array_map('strtoupper', $supportedFormats)) }}
                </p>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <form id="uploadForm" enctype="multipart/form-data">
                    @csrf
                    
                    <div class="mb-6">
                        <label class="block text-gray-700 font-medium mb-2">
                            Select File
                        </label>
                        <input 
                            type="file" 
                            name="file" 
                            id="fileInput"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            required
                        >
                    </div>

                    <div class="border-t pt-4 mb-6">
                        <h3 class="text-lg font-semibold text-gray-700 mb-4">Advanced Options (Optional)</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm text-gray-600 mb-1">Row Threshold (0-1)</label>
                                <input type="number" name="row_threshold" step="0.1" min="0" max="1" placeholder="0.8"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <p class="text-xs text-gray-500 mt-1">Min % of data required to keep a row</p>
                            </div>

                            <div>
                                <label class="block text-sm text-gray-600 mb-1">Column Threshold (0-1)</label>
                                <input type="number" name="col_threshold" step="0.1" min="0" max="1" placeholder="0.8"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <p class="text-xs text-gray-500 mt-1">Min % of data required to keep a column</p>
                            </div>

                            <div class="md:col-span-2 bg-gray-50 p-4 rounded-lg border border-gray-200">
                                <div class="flex justify-between items-center mb-2">
                                    <label class="block text-sm font-medium text-gray-700">
                                        Custom Special Characters
                                    </label>
                                    <button type="button" onclick="toggleCharList()" class="text-xs text-blue-600 hover:underline">
                                        View Default List
                                    </button>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <input type="text" name="special_characters" placeholder="e.g. $,?" 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                        <p class="text-xs text-gray-500 mt-1">Comma separated list of chars</p>
                                    </div>

                                    <div>
                                        <select name="action" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
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
                                <label class="block text-sm text-gray-600 mb-1">Missing Value Imputation Method</label>
                                <select name="imputation_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                    <option value="RDF">Random Forest (RDF) - Best for complex data</option>
                                    <option value="KNN">K-Nearest Neighbors (KNN)</option>
                                    <option value="mean">Mean (Numerical Only)</option>
                                    <option value="median">Median (Numerical Only)</option>
                                    <option value="most_frequent">Most Frequent (Categorical)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <button 
                        type="submit" 
                        id="submitBtn"
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-200"
                    >
                        Start Cleaning Process
                    </button>
                </form>
            </div>

            <div id="progressDiv" class="hidden bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                <div class="flex items-center">
                    <svg class="animate-spin h-5 w-5 mr-3 text-yellow-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="text-yellow-800 font-medium">Running Python scripts... this may take a moment for large datasets.</span>
                </div>
            </div>

            <div id="resultDiv" class="hidden"></div>
        </div>
    </div>

    <script>
        // Toggle the visibility of the default character list
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
            
            // Show progress state
            progressDiv.classList.remove('hidden');
            resultDiv.classList.add('hidden');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Processing File...';
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('{{ route("data-cleaning.upload") }}', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                
                const result = await response.json();
                
                progressDiv.classList.add('hidden');
                resultDiv.classList.remove('hidden');
                
                if (result.status === 'success') {
                    // Extract original file name to create a user-friendly download alias
                    const originalFileName = fileInput.files[0].name.split('.')[0];
                    const prettyDownloadUrl = `${result.download_url}/${originalFileName}_CLEANED.csv`;

                    resultDiv.innerHTML = `
                        <div class="bg-green-50 border border-green-200 rounded-lg p-6">
                            <div class="flex items-start">
                                <svg class="h-6 w-6 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <div class="flex-1">
                                    <h3 class="text-lg font-semibold text-green-800 mb-2">Cleaning Complete!</h3>
                                    <p class="text-green-700 mb-4">${result.message}</p>
                                    <div class="bg-white rounded p-4 mb-4 text-sm shadow-sm border border-green-100">
                                        <p class="mb-1"><strong>Final Rows:</strong> ${result.data.rows}</p>
                                        <p><strong>Final Columns:</strong> ${result.data.columns}</p>
                                    </div>
                                    <a 
                                        href="${prettyDownloadUrl}" 
                                        class="inline-block bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-6 rounded-lg transition duration-200"
                                    >
                                        Download Cleaned CSV
                                    </a>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    this.reset();
                } else {
                    resultDiv.innerHTML = `
                        <div class="bg-red-50 border border-red-200 rounded-lg p-6">
                            <div class="flex items-start">
                                <svg class="h-6 w-6 text-red-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <div>
                                    <h3 class="text-lg font-semibold text-red-800 mb-2">Python Error</h3>
                                    <p class="text-red-700">${result.message || 'The script failed to process your data.'}</p>
                                </div>
                            </div>
                        </div>
                    `;
                }
                
            } catch (error) {
                progressDiv.classList.add('hidden');
                resultDiv.classList.remove('hidden');
                resultDiv.innerHTML = `
                    <div class="bg-red-50 border border-red-200 rounded-lg p-6">
                        <div class="flex items-start">
                            <svg class="h-6 w-6 text-red-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div>
                                <h3 class="text-lg font-semibold text-red-800 mb-2">Server Error</h3>
                                <p class="text-red-700">Communication with the server was interrupted. Check your file size and network connection.</p>
                            </div>
                        </div>
                    </div>
                `;
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Start Cleaning Process';
            }
        });
    </script>
</body>
</html>