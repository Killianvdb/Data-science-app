<x-app-layout :title="'Upload Dataset'">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Upload Dataset
        </h2>
    </x-slot>

    <div class="max-w-3xl mx-auto p-6 bg-white rounded-lg shadow space-y-6">

        @if (session('status') === 'dataset-uploaded')
            <div class="rounded bg-green-100 px-4 py-3 text-green-800">
                Dataset uploaded successfully!
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('datasets.store') }}"
            enctype="multipart/form-data"
            class="space-y-4"
        >
            @csrf

            <div>
                <label class="block font-medium text-gray-700">
                    Dataset file
                </label>

                <input
                    type="file"
                    name="file"
                    required
                    accept=".csv,.xlsx,.xls,.xml"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                >

                @error('file')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
                @enderror

                <p class="text-sm text-gray-500 mt-1">
                    Supported formats: CSV, Excel, XML. Max size: 10MB.
                </p>
            </div>

            <button
                type="submit"
                class="px-6 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700"
            >
                Upload & Start Cleaning
            </button>
        </form>

        <div class="text-xs text-gray-400">
            Files are processed securely and automatically deleted after a limited time.
        </div>
    </div>
</x-app-layout>
