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

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white shadow-md rounded p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Customers by City</h3>
                    <canvas id="cityChart" class="w-full"></canvas>
                </div>

                <div class="bg-white shadow-md rounded p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Age Distribution</h3>
                    <canvas id="ageChart" class="w-full"></canvas>
                </div>

                <div class="bg-white shadow-md rounded p-6 lg:col-span-2">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Average Spending by City</h3>
                    <canvas id="spendChart" class="w-full"></canvas>
                </div>

                <div class="bg-white shadow-md rounded p-6 lg:col-span-2 overflow-hidden">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">Preview</h3>
                        <span class="text-sm text-gray-500">
                            Showing first {{ count($preview) }} rows
                        </span>
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
        new Chart(document.getElementById('cityChart'), {
            type: 'bar',
            data: {
                labels: @json($cityLabels),
                datasets: [{
                    label: 'Customers',
                    data: @json($cityCounts)
                }]
            }
        });

        new Chart(document.getElementById('ageChart'), {
            type: 'bar',
            data: {
                labels: @json($ageLabels),
                datasets: [{
                    label: 'Customers',
                    data: @json($ageBins)
                }]
            }
        });

        new Chart(document.getElementById('spendChart'), {
            type: 'bar',
            data: {
                labels: @json($cityLabels),
                datasets: [{
                    label: 'Average Spending',
                    data: @json($avgSpentByCity)
                }]
            }
        });
    </script>
</x-app-layout>
