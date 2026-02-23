<x-app-layout :title="'File Import - Dashboard'">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('File Import - Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="bg-green-100 text-green-800 px-4 py-2 rounded mb-4">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-100 text-red-800 px-4 py-2 rounded mb-4">
                    {{ session('error') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="bg-red-100 text-red-800 px-4 py-2 rounded mb-4">
                    <ul class="list-disc ml-6">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white shadow-md rounded p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Upload a data file</h3>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <p class="text-sm text-blue-800">
                        <strong>Supported formats:</strong> CSV, XLSX, XLS, XML, JSON, TXT
                    </p>
                </div>

                <form method="POST" action="{{ route('csv.import') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Data File</label>

                        <div id="dropZone"
                            class="flex flex-col items-center justify-center w-full p-8 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100 transition">

                            <svg class="w-10 h-10 text-gray-400 mb-3" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3 16.5V17a2 2 0 002 2h14a2 2 0 002-2v-.5M7.5 12L12 7.5M12 7.5L16.5 12M12 7.5V21" />
                            </svg>

                            <p class="text-gray-600 font-medium">Drag & Drop your file here</p>
                            <p class="text-sm text-gray-400">or click to browse</p>

                            <p id="fileName" class="text-sm text-blue-600 mt-2 hidden"></p>

                            <input type="file"
                                name="file"
                                id="mainFileInput"
                                class="hidden"
                                accept=".csv,.txt,.json,.xml,.xlsx,.xls"
                                required>
                        </div>

                        <p class="text-xs text-gray-500 mt-1">
                            CSV/TXT can be comma, semicolon, tab or pipe separated.
                        </p>
                    </div>

                    <div class="flex flex-col items-center gap-3 text-center">
                        <button type="submit" class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded">
                            Upload and visualize
                        </button>



                        <span class="text-sm text-gray-500">
                            No database, no storage — charts are generated from the uploaded file.
                        </span>
                    </div>
                </form>
            </div>

        </div>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function () {

        const dropZone = document.getElementById("dropZone");
        const fileInput = document.getElementById("mainFileInput");
        const fileName = document.getElementById("fileName");

        dropZone.addEventListener("click", () => fileInput.click());

        fileInput.addEventListener("change", function () {
            if (fileInput.files.length) {
                fileName.textContent = fileInput.files[0].name;
                fileName.classList.remove("hidden");
            }
        });

        dropZone.addEventListener("dragover", function (e) {
            e.preventDefault();
            dropZone.classList.add("border-blue-400", "bg-blue-50");
        });

        dropZone.addEventListener("dragleave", function () {
            dropZone.classList.remove("border-blue-400", "bg-blue-50");
        });

        dropZone.addEventListener("drop", function (e) {
            e.preventDefault();
            dropZone.classList.remove("border-blue-400", "bg-blue-50");

            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                fileName.textContent = e.dataTransfer.files[0].name;
                fileName.classList.remove("hidden");
            }
        });

    });
    </script>

</x-app-layout>
