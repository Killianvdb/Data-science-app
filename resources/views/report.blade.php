<x-app-layout :title="'Visualisation Report'">
    <x-slot name="header">
        <div class="space-y-1">
            <h2 class="font-semibold text-2xl text-gray-900 leading-tight">
                Visualisation Report
            </h2>
            <p class="text-sm text-gray-500">Choose charts, columns and regenerate your report.</p>
        </div>
    </x-slot>

    @php
        $charts = $summary['charts'] ?? [];
        $recommended = $summary['recommendations'] ?? [];
        $autoSelected = $summary['auto_selected'] ?? [];
        $selected = $summary['selected_charts'] ?? array_keys($charts);

        $numericCols = $summary['numeric_columns'] ?? [];
        $catCols = $summary['categorical_columns'] ?? [];

        $chartColumns = $summary['chart_columns'] ?? [];

        $labels = [
            'missing_values' => 'Missing values (bar)',
            'histogram'      => 'Histogram',
            'line'           => 'Line chart',
            'category_bar'   => 'Category bar chart',
        ];

        // Voor display: volg de selected_charts volgorde
        $displayCharts = is_array($selected) ? $selected : array_keys($charts);
    @endphp

    <div class="max-w-5xl mx-auto p-6 space-y-6">

        {{-- Error --}}
        @if(session('error'))
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-red-700 whitespace-pre-line">
                <div class="font-semibold">Error</div>
                <div class="text-sm mt-1">{{ session('error') }}</div>
            </div>
        @endif

        {{-- Summary --}}
        <div class="bg-white rounded-2xl shadow ring-1 ring-black/5 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-3">Dataset summary</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm text-gray-700">
                <div>Rows: <span class="font-semibold">{{ $summary['rows'] ?? 0 }}</span></div>
                <div>Columns: <span class="font-semibold">{{ $summary['columns'] ?? 0 }}</span></div>
                <div>Missing total: <span class="font-semibold">{{ $summary['missing_total'] ?? 0 }}</span></div>
                <div>Duplicate rows: <span class="font-semibold">{{ $summary['duplicate_rows'] ?? 0 }}</span></div>
            </div>
        </div>

        {{-- Recommendations + Selection Panel --}}
        <div class="bg-white rounded-2xl shadow ring-1 ring-black/5 p-6 space-y-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Recommendations</h3>
                    <p class="text-sm text-gray-500">Select one or more charts and choose columns where needed.</p>
                </div>
                <a href="{{ route('visualise.index') }}" class="text-sm text-blue-600 hover:underline">
                    Upload new file
                </a>
            </div>

            <form method="POST" action="{{ route('visualise.update', ['id' => $id]) }}" class="space-y-5" id="chartForm">
                @csrf

                {{-- Recommended checkboxes --}}
                <div class="grid md:grid-cols-2 gap-3">
                    @foreach($recommended as $rec)
                        @php
                            $key = $rec['key'] ?? '';
                            $isChecked = in_array($key, $selected ?? []);
                            $reason = $rec['reason'] ?? '';
                        @endphp

                        <label class="flex gap-3 rounded-xl border p-4 hover:bg-gray-50">
                            <input
                                type="checkbox"
                                class="mt-1"
                                name="charts[]"
                                value="{{ $key }}"
                                {{ $isChecked ? 'checked' : '' }}
                                onchange="toggleOptions('{{ $key }}')"
                            >
                            <div class="flex-1">
                                <div class="font-semibold text-gray-900">{{ $labels[$key] ?? $key }}</div>
                                <div class="text-sm text-gray-600">{{ $reason }}</div>

                                {{-- Column dropdowns --}}
                                <div class="mt-3 space-y-3" id="opts-{{ $key }}">
                                    @if($key === 'histogram' || $key === 'line')
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-600 mb-1">
                                                Select numeric column
                                            </label>
                                            <select
                                                name="chart_columns[{{ $key }}]"
                                                class="w-full rounded-lg border px-3 py-2 text-sm"
                                                {{ empty($numericCols) ? 'disabled' : '' }}
                                            >
                                                @foreach($numericCols as $c)
                                                    <option value="{{ $c }}" {{ (($chartColumns[$key] ?? '') === $c) ? 'selected' : '' }}>
                                                        {{ $c }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @if(empty($numericCols))
                                                <p class="text-xs text-gray-500 mt-1">No numeric columns found.</p>
                                            @endif
                                        </div>
                                    @endif

                                    @if($key === 'category_bar')
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-600 mb-1">
                                                Select categorical column
                                            </label>
                                            <select
                                                name="chart_columns[{{ $key }}]"
                                                class="w-full rounded-lg border px-3 py-2 text-sm"
                                                {{ empty($catCols) ? 'disabled' : '' }}
                                            >
                                                @foreach($catCols as $c)
                                                    <option value="{{ $c }}" {{ (($chartColumns[$key] ?? '') === $c) ? 'selected' : '' }}>
                                                        {{ $c }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @if(empty($catCols))
                                                <p class="text-xs text-gray-500 mt-1">No categorical columns found.</p>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </label>
                    @endforeach
                </div>

                <div class="flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-6 py-3 text-white font-semibold shadow hover:bg-blue-700"
                    >
                        Apply & regenerate
                    </button>

                    <div class="text-xs text-gray-500">
                        Tip: Leave everything blank and click Apply → then Python will use auto-select.
                    </div>
                </div>
            </form>
        </div>

        {{-- Charts display --}}
        <div class="space-y-6">
            <h3 class="text-lg font-semibold text-gray-900">Charts</h3>

            @php
                $base = asset('storage/reports/' . $id);
            @endphp

            @forelse($displayCharts as $key)
                @if(!empty($charts[$key] ?? null))
                    <div class="bg-white rounded-2xl shadow ring-1 ring-black/5 p-6 space-y-4">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <div class="font-semibold text-gray-900">{{ $labels[$key] ?? $key }}</div>
                                @if(!empty($chartColumns[$key] ?? null))
                                    <div class="text-sm text-gray-500">Column: {{ $chartColumns[$key] }}</div>
                                @endif
                            </div>

                            <a
                                href="{{ $base . '/' . $charts[$key] }}"
                                download
                                class="text-sm rounded-lg border px-3 py-2 hover:bg-gray-50"
                            >
                                Download
                            </a>
                        </div>

                        <div class="border rounded-xl bg-gray-50 p-3">
                            <img
                                class="max-w-full h-auto mx-auto rounded-lg"
                                src="{{ $base . '/' . $charts[$key] }}?v={{ time() }}"
                                alt="Chart {{ $key }}"
                            >
                        </div>
                    </div>
                @endif
            @empty
                <div class="text-red-600">No charts to display.</div>
            @endforelse
        </div>
    </div>

    <script>
        function toggleOptions(key) {
            const box = document.querySelector(`input[name="charts[]"][value="${key}"]`);
            const opts = document.getElementById(`opts-${key}`);
            if (!opts) return;

            // Show/hide dropdown area depending on checkbox
            opts.style.display = box && box.checked ? "block" : "none";
        }

        // Init on load
        ["missing_values","histogram","line","category_bar"].forEach(k => toggleOptions(k));
    </script>
</x-app-layout>
