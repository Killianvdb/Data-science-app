<x-app-layout :title="'Contact Us'">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Contact Us
        </h2>
    </x-slot>

<br>

    <div class="max-w-3xl mx-auto p-6 bg-white rounded-lg shadow space-y-6">

        @if(session('success'))
            <div class="p-4 bg-green-100 text-green-800 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        <p>If you have any questions, feedback, or need support, please fill out the form below and we will get back to you as soon as possible.</p>

        <form method="POST" action="{{ route('contact.submit') }}" class="space-y-4">
            @csrf

            <div>
                <label for="name" class="block font-medium text-gray-700">Name</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" required
                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                @error('name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div>
                <label for="email" class="block font-medium text-gray-700">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required
                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                @error('email') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div>
                <label for="message" class="block font-medium text-gray-700">Message</label>
                <textarea id="message" name="message" rows="5" required
                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">{{ old('message') }}</textarea>
                @error('message') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div>
                <button type="submit"
                    class="px-6 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    Send Message
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
