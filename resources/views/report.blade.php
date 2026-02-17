<x-app-layout :title="'Visualisation'">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Visualisation Report
        </h2>
    </x-slot>

    @php
        $charts = $summary['charts'] ?? [];

        // Labels voor de knoppen
        $labels = [
            'missing' => 'Missing (bar)',
            'line'    => 'Lijn grafiek',
            'pie'     => 'Taart grafiek',
        ];

        // Kies een default chart die bestaat
        $defaultKey = null;
        foreach (['missing','line','pie'] as $k) {
            if (!empty($charts[$k])) { $defaultKey = $k; break; }
        }
    @endphp

    <div class="max-w-4xl mx-auto p-6 space-y-6">

        {{-- SUMMARY CARD --}}
        <div class="bg-white rounded-xl shadow p-6 space-y-2">
            <h1 class="text-2xl font-semibold">Resultaat</h1>

            <div class="grid grid-cols-2 gap-4 text-sm text-gray-700">
                <p>Rows: <span class="font-semibold">{{ $summary['rows'] ?? 0 }}</span></p>
                <p>Columns: <span class="font-semibold">{{ $summary['columns'] ?? 0 }}</span></p>
                <p>Missing total: <span class="font-semibold">{{ $summary['missing_total'] ?? 0 }}</span></p>
                <p>Duplicate rows: <span class="font-semibold">{{ $summary['duplicate_rows'] ?? 0 }}</span></p>
            </div>
        </div>

        {{-- CHARTS CARD --}}
        <div class="bg-white rounded-xl shadow p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">Grafieken</h2>
                <p class="text-sm text-gray-500">Kies een grafiek type</p>
            </div>

            {{-- 3 KNOPPEN (toon altijd) --}}
            <div class="flex flex-wrap gap-3">
                @foreach (['missing','line','pie'] as $key)
                    <button
                        type="button"
                        id="btn-{{ $key }}"
                        class="px-4 py-2 rounded-lg border text-sm
                               bg-gray-100 hover:bg-gray-200
                               disabled:opacity-50 disabled:cursor-not-allowed"
                        onclick="setChart('{{ $key }}')"
                        {{ empty($charts[$key] ?? null) ? 'disabled' : '' }}
                    >
                        {{ $labels[$key] }}
                    </button>
                @endforeach
            </div>

            {{-- CHART PREVIEW --}}
            @if($defaultKey)
                <div class="border rounded-xl p-3 bg-gray-50">
                    <img
                        id="mainChart"
                        class="max-w-full h-auto mx-auto rounded-lg"
                        src="{{ asset('storage/reports/' . $id . '/' . $charts[$defaultKey]) }}?v={{ time() }}"
                        alt="Chart"
                    >
                </div>

                {{-- Download button --}}
                {{-- <div class="flex justify-end">
                    <a
                        id="downloadBtn"
                        href="{{ asset('storage/reports/' . $id . '/' . $charts[$defaultKey]) }}"
                        download
                        class="inline-flex items-center gap-2 px-5 py-2 rounded-lg
                               bg-blue-600 text-white hover:bg-blue-700"
                    >
                        Download deze grafiek
                    </a>
                </div> --}}
            @else
                <p class="text-red-600">
                    Geen grafieken gevonden (summary.json bevat geen charts).
                </p>
            @endif
        </div>

        {{-- UPLOAD AGAIN --}}
        <div class="bg-white rounded-xl shadow p-6 space-y-4">
            <h2 class="text-lg font-semibold">Upload een andere file</h2>

            @if(session('error'))
                <div class="p-3 rounded bg-red-50 text-red-700 border border-red-200 whitespace-pre-line">
                    {{ session('error') }}
                </div>
            @endif

            <form action="{{ route('visualise.generate') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <input type="file" name="dataset" required class="block w-full border rounded p-2">

                <button type="submit" class="px-5 py-2 rounded-lg bg-gray-900 text-white hover:bg-black">
                    Generate Report
                </button>
            </form>
        </div>

    </div>

    <script>
        const charts = @json($charts);
        const basePath = "{{ asset('storage/reports/' . $id) }}";

        function resetButtons() {
            ["missing","line","pie"].forEach(k => {
                const btn = document.getElementById("btn-" + k);
                if (!btn) return;

                btn.classList.remove("bg-blue-600","text-white","hover:bg-blue-700");
                btn.classList.add("bg-gray-100","hover:bg-gray-200");
            });
        }

        function setChart(key) {
            if (!charts[key]) return;

            const img = document.getElementById("mainChart");
            const downloadBtn = document.getElementById("downloadBtn");

            const file = charts[key];
            const url = `${basePath}/${file}`;

            img.src = `${url}?v=${Date.now()}`; // cache-buster
            downloadBtn.href = url;

            resetButtons();
            const btn = document.getElementById("btn-" + key);
            if (btn) {
                btn.classList.remove("bg-gray-100","hover:bg-gray-200");
                btn.classList.add("bg-blue-600","text-white","hover:bg-blue-700");
            }
        }

        // Zet default knop actief als er 1 bestaat
        const first = Object.keys(charts)[0];
        if (first) setChart(first);
    </script>
</x-app-layout>
