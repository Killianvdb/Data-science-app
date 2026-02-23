<x-app-layout :title="'CSV Dashboard'">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('CSV Dashboard') }}
            </h2>

            <a href="{{ route('csv.form') }}" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-3 py-1 rounded">
                Import another file
            </a>
        </div>
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

            <div class="bg-white shadow-md rounded p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-3">Detected column types</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Column</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($types as $col => $type)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-center align-middle">{{ $col }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center align-middle">
                                    <span class="inline-flex items-center px-2 py-1 rounded text-sm
                                        {{ $type === 'numeric' ? 'bg-blue-100 text-blue-800' : '' }}
                                        {{ $type === 'date' ? 'bg-purple-100 text-purple-800' : '' }}
                                        {{ $type === 'categorical' ? 'bg-green-100 text-green-800' : '' }}
                                        {{ $type === 'text' ? 'bg-gray-100 text-gray-800' : '' }}
                                    ">
                                        {{ $type }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @if(count($charts) === 0)
                <div class="bg-white shadow-md rounded p-6 mb-6">
                    <p class="text-gray-700">
                        No charts could be generated from this file. Try a CSV with at least one numeric column
                        (for histograms) or one categorical column (for top categories).
                    </p>
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                @foreach($charts as $i => $chart)
                    <div class="bg-white shadow-md rounded p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">{{ $chart['title'] }}</h3>
                        <canvas id="chart_{{ $i }}" class="w-full"></canvas>
                    </div>
                @endforeach

                <div class="bg-white shadow-md rounded p-6 lg:col-span-2 overflow-hidden">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">Preview</h3>
                        <span class="text-sm text-gray-500">Showing first {{ count($preview) }} rows</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                            <tr>
                                @if(count($preview))
                                    @foreach(array_keys($preview[0]) as $header)
                                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ $header }}
                                        </th>
                                    @endforeach
                                @endif
                            </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($preview as $row)
                                <tr class="hover:bg-gray-50">
                                    @foreach($row as $value)
                                        <td class="px-6 py-4 whitespace-nowrap text-center align-middle">
                                            {{ $value }}
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const charts = @json($charts);

        charts.forEach((c, i) => {
            new Chart(document.getElementById(`chart_${i}`), {
                type: c.type,
                data: {
                    labels: c.labels,
                    datasets: [{
                        label: c.title,
                        data: c.data
                    }]
                }
            });
        });
    </script>
</x-app-layout>
