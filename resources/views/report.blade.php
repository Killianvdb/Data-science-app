<x-app-layout :title="'Visualisation Report'">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Visualisation
        </h2>
    </x-slot>

    @php
        $chartMeta = [
            'missing_values' => ['label' => 'Missing values', 'needs' => null],
            'dtypes'         => ['label' => 'Column types', 'needs' => null],
            'histogram'      => ['label' => 'Histogram', 'needs' => 'numeric'],
            'pie'            => ['label' => 'Pie chart', 'needs' => 'categorical'],
            'line'           => ['label' => 'Line chart', 'needs' => 'numeric'],
        ];

        $columnNames = $summary['column_names'] ?? [];
        $numericCols = $summary['numeric_columns'] ?? [];
        $catCols     = $summary['categorical_columns'] ?? [];

        $currentCharts = array_keys($summary['charts'] ?? []);
        if (empty($currentCharts)) {
            $currentCharts = ['missing_values', 'dtypes'];
        }

        $currentChartCols = $summary['chart_columns'] ?? [];
    @endphp

    {{-- Loading overlay --}}
    <div id="vizLoader" class="hidden fixed inset-0 bg-black/50 z-50 items-center justify-center">
        <div class="bg-white rounded-lg p-6 shadow-lg flex items-center gap-4">
            <div class="w-6 h-6 border-4 border-gray-300 border-t-gray-800 rounded-full animate-spin"></div>
            <div>
                <div class="font-semibold">Generating charts...</div>
                <div class="text-sm text-gray-600">Running Python scripts, please wait.</div>
            </div>
        </div>
    </div>

    <div class="max-w-5xl mx-auto p-6 space-y-6">

        @if(session('error'))
            <div class="p-3 rounded bg-red-100 text-red-800">
                {!! nl2br(e(session('error'))) !!}
            </div>
        @endif

        <div class="bg-white rounded-xl shadow p-6 space-y-3">
            <h1 class="text-xl font-semibold">Visualization Report</h1>

            <p>Rows: <b>{{ $summary['rows'] ?? '-' }}</b></p>
            <p>Columns: <b>{{ $summary['columns'] ?? '-' }}</b></p>
            <p>Missing total: <b>{{ $summary['missing_total'] ?? '-' }}</b></p>
            <p>Duplicate rows: <b>{{ $summary['duplicate_rows'] ?? '-' }}</b></p>
        </div>

        {{-- SELECT + APPLY --}}
        <form data-show-loader action="{{ route('visualise.update', ['id' => $id]) }}" method="POST" class="bg-white rounded-xl shadow p-6 space-y-4">
            @csrf

            <h2 class="text-lg font-semibold">Select charts</h2>

            <details class="border rounded-lg p-3">
                <summary class="cursor-pointer select-none font-medium">
                    Choose charts ({{ count($currentCharts) }} selected)
                </summary>

                <div class="mt-3 space-y-2">
                    @foreach($chartMeta as $key => $meta)
                        <label class="flex items-center gap-2">
                            <input
                                type="checkbox"
                                name="charts[]"
                                value="{{ $key }}"
                                @checked(in_array($key, $currentCharts))
                            >
                            <span>{{ $meta['label'] }}</span>
                        </label>
                    @endforeach
                </div>
            </details>

            <div class="text-sm text-gray-600">
                Tip: voor <b>Histogram</b> en <b>Line chart</b kies je een <b>numerieke</b> kolom. Voor <b>Pie chart</b kies je een <b>categorische</b> kolom.
            </div>

            <button type="submit" class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700">
                Apply (regenerate)
            </button>
        </form>

        {{-- CHARTS --}}
        <div class="space-y-6">
            @foreach(($summary['charts'] ?? []) as $chartKey => $filename)
                @php
                    $meta = $chartMeta[$chartKey] ?? ['label' => $chartKey, 'needs' => null];

                    $allowedCols = [];
                    if ($meta['needs'] === 'numeric') $allowedCols = $numericCols;
                    if ($meta['needs'] === 'categorical') $allowedCols = $catCols;

                    $selectedCol = $currentChartCols[$chartKey] ?? null;

                    $imgUrl = asset('storage/reports/' . $id . '/' . $filename);
                @endphp

                <form data-show-loader action="{{ route('visualise.update', ['id' => $id]) }}" method="POST" class="bg-white rounded-xl shadow overflow-hidden">
                    @csrf

                    {{-- keep current selected charts when updating 1 chart --}}
                    @foreach($currentCharts as $keepChart)
                        <input type="hidden" name="charts[]" value="{{ $keepChart }}">
                    @endforeach

                    {{-- keep current chart column selections --}}
                    @foreach(($currentChartCols ?? []) as $k => $v)
                        @if($k !== $chartKey)
                            <input type="hidden" name="chart_columns[{{ $k }}]" value="{{ $v }}">
                        @endif
                    @endforeach

                    {{-- TOP BAR (per chart) --}}
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 bg-gray-50 px-4 py-3 border-b">
                        <div class="font-semibold">{{ $meta['label'] }}</div>

                        <div class="flex items-center gap-2">
                            @if($meta['needs'] !== null)
                                <select name="chart_columns[{{ $chartKey }}]" class="border rounded px-3 py-2">
                                    <option value="">Auto (first valid)</option>
                                    @foreach($allowedCols as $col)
                                        <option value="{{ $col }}" @selected($selectedCol === $col)>{{ $col }}</option>
                                    @endforeach
                                </select>
                            @endif

                            <button type="submit" class="px-3 py-2 rounded bg-blue-600 text-white hover:bg-blue-700">
                                Update
                            </button>

                            <a href="{{ $imgUrl }}" download class="px-3 py-2 rounded bg-green-600 text-white hover:bg-green-700">
                                Download
                            </a>
                        </div>
                    </div>

                    {{-- IMAGE --}}
                    <div class="p-4">
                        <img class="max-w-full h-auto" src="{{ $imgUrl }}?v={{ time() }}" alt="{{ $meta['label'] }}">
                    </div>
                </form>
            @endforeach
        </div>

        <hr class="my-6">

        {{-- Upload new file --}}
        <div class="bg-white rounded-xl shadow p-6 space-y-3">
            <h2 class="text-lg font-semibold">Upload another file</h2>

            <form data-show-loader action="{{ route('visualise.generate') }}" method="POST" enctype="multipart/form-data" class="space-y-3">
                @csrf

                <input type="file" name="dataset" required class="border rounded p-2 w-full">

                <button type="submit" class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700">
                    Generate Report
                </button>
            </form>
        </div>
    </div>

    <script>
        (function () {
            const loader = document.getElementById('vizLoader');

            document.querySelectorAll('form[data-show-loader]').forEach((form) => {
                form.addEventListener('submit', () => {
                    loader.classList.remove('hidden');
                    loader.classList.add('flex');
                });
            });
        })();
    </script>
</x-app-layout>
