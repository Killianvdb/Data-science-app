<style>
  /* --- PDF MODE --- */
  body.pdf-export #reportContent{
    /* A4 landscape width: 297mm
       margin izquierda+derecha: 10mm + 10mm => ancho útil = 277mm */
    width: 277mm !important;
    max-width: 277mm !important;

    margin: 0 auto !important;
    padding: 0 !important;
    box-sizing: border-box;
  }

  /* Quita paddings de Tailwind dentro del report en modo PDF */
  body.pdf-export #reportContent.sm\:px-6,
  body.pdf-export #reportContent.lg\:px-8{
    padding-left: 0 !important;
    padding-right: 0 !important;
  }

  /* Chart container: no recortes en PDF */
  body.pdf-export #reportContent .chart-box{
    width: 100% !important;
    max-width: 100% !important;
    overflow: visible !important;
    box-sizing: border-box;
    padding: 0 6mm !important; /* aire para ejes */
  }

  .chart-canvas{ width:100% !important; height:100% !important; display:block; }
</style>

<x-app-layout :title="'Data File Dashboard'">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Data File Dashboard') }}
            </h2>
        </div>
    </x-slot>

    <div class="flex items-center justify-between">

        <div>
            <a href="{{ route('csv.form') }}"
            class="inline-block border border-gray-300 text-gray-700 hover:bg-gray-100 px-4 py-2 rounded-lg">
                Choose another file
            </a>

        </div>

        <div>
            <button id="downloadPdfBtn"
                class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg">
                Download PDF
            </button>
        </div>

    </div>

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

            <div class="bg-white shadow-md rounded p-6 mb-6 pdf-table">
                <h3 class="text-lg font-semibold text-gray-800 mb-3 text-center">Detected column types</h3>
                <div class="overflow-x-auto pdf-no-overflow">
                    <table class="min-w-full divide-y divide-gray-200 pdf-table-center">
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
                        No charts could be generated from this file.
                        Make sure your file (CSV, TXT, JSON, XML, XLSX or XLS)
                        contains at least one numeric column (for histograms)
                        or one categorical column (for category charts).
                    </p>

                </div>
            @endif

            <div id="reportContent" class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-6">

                    @foreach($charts as $i => $chart)
                        <div class="bg-white shadow-md rounded p-6">
                            <div class="pdf-avoid-break">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4 text-center">{{ $chart['title'] }}</h3>

                                <div class="chart-box mx-auto overflow-hidden" style="max-width: 800px; width: 100%; height: 340px;">
                                    <canvas id="chart_{{ $i }}" class="chart-canvas"></canvas>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

            </div>

        </div>
    </div>

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

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>


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
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                    padding: { left: 20, right: 10, top: 10, bottom: 10 }},
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true },
                        x: { ticks: { maxRotation: 45, minRotation: 0 } }
                    }
                }
            });
        });


        document.getElementById("downloadPdfBtn").addEventListener("click", async function () {
            const element = document.querySelector("#reportContent");
            const fileName = "{{ isset($fileName) ? pathinfo($fileName, PATHINFO_FILENAME) : 'insights' }}";

            document.body.classList.add("pdf-export");

            // Espera a que el CSS de pdf-export se aplique antes de capturar
            await new Promise(r => requestAnimationFrame(r));

            const opt = {
                margin: [10, 10, 10, 10], // mm: top, left, bottom, right (html2pdf usa array como [top,left,bottom,right])
                filename: fileName + "-insights.pdf",
                image: { type: "jpeg", quality: 0.98 },
                html2canvas: {
                scale: 2,
                useCORS: true,
                backgroundColor: "#ffffff",
                scrollX: 0,
                scrollY: 0
                },
                jsPDF: { unit: "mm", format: "a4", orientation: "landscape" },
                pagebreak: { mode: ["avoid-all", "css", "legacy"] }
            };

            html2pdf()
                .set(opt)
                .from(element)
                .save()
                .then(() => document.body.classList.remove("pdf-export"))
                .catch(() => document.body.classList.remove("pdf-export"));
            });

    </script>

</x-app-layout>
