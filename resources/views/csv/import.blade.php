<x-app-layout :title="'CSV Import - Dashboard'">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('CSV Import - Dashboard') }}
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
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Upload a CSV file</h3>

                <form method="POST" action="{{ route('csv.import') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">CSV File</label>
                        <input
                            type="file"
                            name="file"
                            accept=".csv,.txt"
                            required
                            class="w-full border rounded px-4 py-3 text-lg focus:outline-none focus:ring-2 focus:ring-blue-400"
                        >
                        <p class="text-xs text-gray-500 mt-2">
                            Supported: .csv, .txt (comma, semicolon or tab separated)
                        </p>
                    </div>

                    <div class="flex items-center gap-3">
                        <button
                            type="submit"
                            class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded"
                        >
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
</x-app-layout>
