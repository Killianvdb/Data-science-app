<x-app-layout :title="'Visualise'">
    <x-slot name="header">
        <div class="space-y-1">
            <h2 class="font-semibold text-2xl text-gray-900 leading-tight">
                Visualisation
            </h2>
            <p class="text-sm text-gray-500">
                Clean, analyze and improve your datasets with confidence
            </p>
        </div>
    </x-slot>

    <div class="max-w-4xl mx-auto p-6">
        {{-- Error box --}}
        @if(session('error'))
            <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-red-700 whitespace-pre-line">
                <div class="font-semibold">Error</div>
                <div class="text-sm mt-1">{{ session('error') }}</div>
            </div>
        @endif

        {{-- Main card --}}
        <div class="rounded-2xl bg-white/70 backdrop-blur shadow-lg ring-1 ring-black/5 p-6 md:p-8">
            <div class="space-y-2">
                <h3 class="text-lg font-semibold text-gray-900">Upload your dataset</h3>
                <p class="text-sm text-gray-600">
                    Supported formats: <span class="font-medium">CSV</span>, <span class="font-medium">XLSX</span>, <span class="font-medium">XLS</span>
                </p>
            </div>

            <form class="mt-6 space-y-6" action="{{ route('visualise.generate') }}" method="POST" enctype="multipart/form-data">
                @csrf

                {{-- Upload zone --}}
                <label class="block">
                    <span class="sr-only">Choose file</span>

                    <div class="rounded-2xl border border-dashed border-blue-200 bg-blue-50/50 p-6 hover:bg-blue-50 transition">
                        <div class="flex flex-col gap-3">
                            <div class="flex items-start gap-3">
                                <div class="mt-1 inline-flex h-10 w-10 items-center justify-center rounded-xl bg-blue-600 text-white shadow">
                                    {{-- simple icon --}}
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M7 10l5-5m0 0l5 5m-5-5v12" />
                                    </svg>
                                </div>

                                <div class="flex-1">
                                    <p class="font-medium text-gray-900">Select a file to generate your report</p>
                                    <p class="text-sm text-gray-600">Click the button or browse to choose a dataset.</p>
                                </div>
                            </div>

                            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                                <input
                                    id="dataset"
                                    type="file"
                                    name="dataset"
                                    required
                                    class="block w-full text-sm text-gray-700
                                           file:mr-4 file:rounded-xl file:border-0
                                           file:bg-blue-600 file:px-5 file:py-3
                                           file:text-white file:font-semibold
                                           hover:file:bg-blue-700
                                           focus:outline-none"
                                />

                                <button
                                    type="submit"
                                    class="inline-flex w-full sm:w-auto items-center justify-center rounded-xl
                                           bg-blue-600 px-6 py-3 text-white font-semibold
                                           shadow hover:bg-blue-700 active:bg-blue-800
                                           focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2"
                                >
                                    Generate Report
                                </button>
                            </div>

                            {{-- filename preview --}}
                            <p id="fileName" class="text-sm text-gray-600">
                                No file selected / lol
                            </p>
                        </div>
                    </div>
                </label>
            </form>
            {{-- <div id="progressDiv" class="hidden bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <div class="flex items-center">
                    <svg class="animate-spin h-5 w-5 mr-3 text-yellow-600" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="text-yellow-800 font-medium">Running Python scripts... this may take a moment.</span>
                </div>
            </div> --}}
        </div>
    </div>
    {{-- Kleine JS om bestandsnaam te tonen --}}
    <script>
        const input = document.getElementById('dataset');
        const fileName = document.getElementById('fileName');

        if (input && fileName) {
            input.addEventListener('change', () => {
                fileName.textContent = input.files?.[0]?.name ? `Selected: ${input.files[0].name}` : 'No file selected';
            });
        }
    </script>
</x-app-layout>
