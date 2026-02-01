<x-app-layout :title="'How It Works'">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            How It Works
        </h2>
    </x-slot>

    <div class="max-w-4xl mx-auto p-6 space-y-6">
        <p>CleanMyData is a web application designed to make data cleaning and management simple, accessible, and affordable. Here's how it works:</p>

        <h2 class="text-2xl font-semibold mt-6 mb-2">1. Upload Your Dataset</h2>
        <p>Users can upload their datasets in CSV format. CleanMyData supports multiple datasets and automatically detects columns and rows.</p>

        <h2 class="text-2xl font-semibold mt-6 mb-2">2. Inspect & Clean Data</h2>
        <p>The platform helps you detect duplicates, empty columns, and inconsistent data types. You can choose which cleaning operations to apply.</p>

        <h2 class="text-2xl font-semibold mt-6 mb-2">3. Merge & Export</h2>
        <p>If you have multiple datasets, CleanMyData can intelligently merge them while keeping column structures aligned. Once cleaned, you can export your data for analysis.</p>

        <h2 class="text-2xl font-semibold mt-6 mb-2">4. Advanced Analysis with Python</h2>
        <p>After cleaning, datasets are ready for advanced processing. Users can integrate with Python scripts for deeper analysis, statistical modeling, or machine learning.</p>

        <h2 class="text-2xl font-semibold mt-6 mb-2">5. Privacy & Security</h2>
        <p>All datasets are stored securely in a PostgreSQL database. We do not share user data, and all sensitive information is protected with industry-standard security practices.</p>
    </div>
</x-app-layout>
