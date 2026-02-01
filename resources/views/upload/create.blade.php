<x-app-layout :title="'Upload Dataset'">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Upload Dataset</h2>
    </x-slot>

    <div class="max-w-3xl mx-auto p-6 bg-white rounded-lg shadow">
        @if(session('success'))
            <div class="p-4 bg-green-100 text-green-800 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        <form method="POST" action="{{ route('upload.store') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf

            <div>
                <label for="file" class="block font-medium text-gray-700">Select file (CSV, Excel, XML)</label>
                <input type="file" name="file" id="file" required
                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                @error('file') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div>
                <button type="submit"
                    class="px-6 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                    Upload
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
