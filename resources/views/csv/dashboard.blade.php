<style>
  #pdfPreviewContent .chart-box{
  max-width: 800px;
  height: 340px;
}

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




            <div class="bg-white shadow-md rounded p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Create a chart</h3>

                <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                    <div>
                    <label class="text-sm text-gray-600">Chart type</label>
                    <select id="chartType" class="w-full border rounded p-2">
                        <option value="bar">Bar</option>
                        <option value="line">Line</option>
                        <option value="pie">Pie</option>
                        <option value="doughnut">Doughnut</option>
                        <option value="scatter">Scatter</option>
                        <option value="histogram">Histogram</option>
                    </select>
                    </div>

                    <div>
                    <label class="text-sm text-gray-600">X column</label>
                    <select id="xCol" class="w-full border rounded p-2"></select>
                    </div>

                    <div>
                    <label class="text-sm text-gray-600">Y column</label>
                    <select id="yCol" class="w-full border rounded p-2"></select>
                    </div>

                    <div>
                    <label class="text-sm text-gray-600">Aggregation</label>
                    <select id="agg" class="w-full border rounded p-2">
                        <option value="count">count</option>
                        <option value="sum">sum</option>
                        <option value="avg">avg</option>
                    </select>
                    </div>

                    <div class="flex items-end">
                    <button id="addChartBtn" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg">
                        Add to report
                    </button>
                    </div>
                </div>
                </div>

                <div class="bg-white shadow-md rounded p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Charts to include in PDF</h3>
                <p class="text-sm text-gray-500 mb-4">Click a chart to move it to Suggestions.</p>
                <div id="includedCharts" class="grid grid-cols-1 gap-6"></div>
                </div>


                <div class="bg-white shadow-md rounded p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-2">
                    Suggestions <span class="text-sm text-gray-500">(those ones will not be downloaded in the rapport)</span>
                </h3>
                <p class="text-sm text-gray-500 mb-4">Click a suggestion to include it in your rapport.</p>
                <div id="suggestedCharts" class="space-y-2">

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

        <div class="bg-white shadow-md rounded p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-2">PDF Preview</h3>
            <p class="text-sm text-gray-500 mb-4">This is exactly how your PDF will look.</p>

            <div id="pdfPreviewContent">
                <div id="pdfPreviewCharts" class="grid grid-cols-1 gap-6"></div>
            </div>

        </div>

        <div class="flex justify-center mt-4">
            <button id="downloadPdfBtnBottom"
                class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg">
                Download PDF
            </button>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>



    <script>

    //console.log("JS loaded");
    //console.log({ previewRows: (@json($preview)).length, types: @json($types) });

        if (window.Chart && window.ChartDataLabels) {
            Chart.register(ChartDataLabels);
        } else {
            console.warn("Chart or ChartDataLabels not loaded");
        }


        const fileName = "{{ isset($fileName) ? pathinfo($fileName, PATHINFO_FILENAME) : 'insights' }}";

        document.getElementById("downloadPdfBtn").onclick = async () => {

            const element = document.getElementById("pdfPreviewContent");

            const opt = {
                margin: [10, 10, 10, 10],
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

        await html2pdf().set(opt).from(element).save();

        };

        // for the button at the bottom as well (i know there is another way to do it but i am having problems with formats...)

        document.getElementById("downloadPdfBtnBottom").onclick = async () => {

            const element = document.getElementById("pdfPreviewContent");

            const opt = {
                margin: [10, 10, 10, 10],
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

        await html2pdf().set(opt).from(element).save();

        };

        const rows = @json($preview);
        const columnTypes = @json($types);
        const columns = Object.keys(columnTypes);

        const included = [];
        const suggested = [];
        const chartInstances = new Map();

        const MAX_CATEGORIES = 10;
        const HIST_BINS = 12;


        const xSel = document.getElementById("xCol");
        const ySel = document.getElementById("yCol");
        const typeSel = document.getElementById("chartType");

        function fillSelects() {
            xSel.innerHTML = columns.map(c => `<option value="${escAttr(c)}">${escHtml(c)} (${columnTypes[c]})</option>`).join("");

            const nums = columns.filter(c => columnTypes[c] === "numeric");
            ySel.innerHTML = `<option value="">(none)</option>` + nums.map(c => `<option value="${escAttr(c)}">${escHtml(c)} (numeric)</option>`).join("");
        }
        fillSelects();

        function syncBuilder() {
            const t = typeSel.value;
            const needsY = (t === "bar" || t === "line" || t === "scatter");
            const isHist = (t === "histogram");
            document.getElementById("yCol").disabled = !needsY || isHist;
            document.getElementById("agg").disabled = isHist || !(t === "bar" || t === "line" || t === "pie" || t === "doughnut");
        }
        typeSel.addEventListener("change", syncBuilder);
        syncBuilder();

        function buildSuggestions() {
            suggested.length = 0;
            const cats = columns.filter(c => columnTypes[c] === "categorical");
            const nums = columns.filter(c => columnTypes[c] === "numeric");

            if (cats[0]) {
            suggested.push({ id: "s1", title: `Pie: ${cats[0]} (count)`, spec: { type:"pie", x: cats[0], y:null, agg:"count" } });
            suggested.push({ id: "s2", title: `Doughnut: ${cats[0]} (count)`, spec: { type:"doughnut", x: cats[0], y:null, agg:"count" } });
            }
            if (nums[0]) {
            suggested.push({ id: "s3", title: `Histogram: ${nums[0]}`, spec: { type:"histogram", x: nums[0], y:null, agg:"count" } });
            }
            renderSuggested();
        }
        buildSuggestions();

        function isNumberLike(v){ if(v===null||v===undefined) return false; const s=String(v).trim(); if(!s) return false; return Number.isFinite(Number(s.replace(",", "."))); }
        function toNumber(v){ return Number(String(v).trim().replace(",", ".")); }

        function buildChart(spec) {
            const id = (crypto.randomUUID ? crypto.randomUUID() : String(Date.now())+Math.random());
            const { type, x, y, agg } = spec;

            if (type === "histogram") {
            if (columnTypes[x] !== "numeric") throw new Error("Histogram needs numeric X");
            const vals = rows.map(r => r[x]).filter(isNumberLike).map(toNumber);
            if (!vals.length) throw new Error("No numeric values");
            const min = Math.min(...vals), max = Math.max(...vals);
            const width = (max - min) / HIST_BINS || 1;
            const counts = new Array(HIST_BINS).fill(0);
            for (const v of vals) {
                let i = Math.floor((v - min)/width);
                if (i >= HIST_BINS) i = HIST_BINS-1;
                if (i < 0) i = 0;
                counts[i]++;
            }
            const labels = counts.map((_, i) => `${roundNice(min+i*width)}–${roundNice(min+(i+1)*width)}`);
            return { id, title: `Histogram: ${x}`, type:"bar", labels, data:counts, spec };
            }

            if (type === "scatter") {
            if (!y || columnTypes[x] !== "numeric" || columnTypes[y] !== "numeric") throw new Error("Scatter needs numeric X and Y");
            const pts = [];
            for (const r of rows) if (isNumberLike(r[x]) && isNumberLike(r[y])) pts.push({ x: toNumber(r[x]), y: toNumber(r[y]) });
            return { id, title: `Scatter: ${y} vs ${x}`, type:"scatter", data:pts, labels:[], spec, options:{ plugins:{legend:{display:false}} } };
            }

            const groups = new Map();
            for (const r of rows) {
            const key = (r[x] ?? "").toString().trim() || "(empty)";
            if (!groups.has(key)) groups.set(key, { count:0, sum:0 });
            const g = groups.get(key);
            g.count++;
            if (y && isNumberLike(r[y])) g.sum += toNumber(r[y]);
            }

            let entries = Array.from(groups.entries()).map(([k,v]) => ({ k, count:v.count, sum:v.sum, avg: v.count ? v.sum/v.count : 0 }));
            entries.sort((a,b)=> b.count-a.count);

            if (entries.length > MAX_CATEGORIES) entries = entries.slice(0, MAX_CATEGORIES);

            const labels = entries.map(e=>e.k);
            let data, metric;
            if (agg === "sum") { if(!y) throw new Error("sum needs Y"); data = entries.map(e=>roundNice(e.sum)); metric=`sum(${y})`; }
            else if (agg === "avg") { if(!y) throw new Error("avg needs Y"); data = entries.map(e=>roundNice(e.avg)); metric=`avg(${y})`; }
            else { data = entries.map(e=>e.count); metric="count"; }

            return { id, title: `${cap(type)}: ${x} (${metric})`, type, labels, data, spec };
        }

        function destroyChart(canvasId){ const inst=chartInstances.get(canvasId); if(inst){inst.destroy(); chartInstances.delete(canvasId);} }

        function draw(canvasId, c) {
            destroyChart(canvasId);

            const el = document.getElementById(canvasId);
            const isPie = c.type === "pie" || c.type === "doughnut";
            const isScatter = c.type === "scatter";
            const isLine = c.type === "line";
            const isBar = c.type === "bar";

            const cfg = {
                type: c.type,
                data: isScatter
                ? { datasets: [{ label: c.title, data: c.data }] }
                : { labels: c.labels, datasets: [{ label: c.title, data: c.data }] },

                options: {
                responsive: true,
                maintainAspectRatio: false,

                plugins: {
                    legend: { display: isPie },

                    datalabels: {
                    display: (ctx) => {
                        if (isScatter) return false;

                        if (isPie) return true;

                        if (isBar) return true;

                        if (isLine) return false;

                        return false;
                    },

                    formatter: (value, ctx) => {
                        if (isPie) {
                        const dataArr = ctx.chart.data.datasets[0].data || [];
                        const total = dataArr.reduce((a, b) => a + (Number(b) || 0), 0) || 1;
                        const pct = (Number(value) || 0) / total * 100;
                        const txt = pct >= 10 ? pct.toFixed(0) : pct.toFixed(1);
                        return txt + "%";
                        }

                        if (isBar) {
                        return String(value);
                        }

                        return "";
                    },

                    anchor: isPie ? "center" : "end",
                    align: isPie ? "center" : "end",
                    clamp: true,
                    offset: isPie ? 0 : 2,
                    font: { size: 12, weight: "bold" }
                    }
                },

                ...(isPie || isScatter ? {} : {
                    scales: {
                    y: { beginAtZero: true, grace: "15%" },
                    x: { ticks: { maxRotation: 45, minRotation: 0 } }
                    }
                }),

                ...(c.options || {})
                }
            };

            chartInstances.set(canvasId, new Chart(el, cfg));
            }


        function renderIncluded() {

            const wrap = document.getElementById("includedCharts");
            const pdfWrap = document.getElementById("pdfPreviewContent");

            wrap.innerHTML = "";
            pdfWrap.innerHTML = "";

            included.forEach(c => {

                const incId = `inc_${c.id}`;
                const pdfId = `pdf_${c.id}`;

                const screenCard = document.createElement("div");
                screenCard.className = "bg-white shadow-md rounded p-6 cursor-pointer";
                screenCard.innerHTML = `
                <h3 class="text-lg font-semibold text-gray-800 mb-4 text-center">${escHtml(c.title)}</h3>
                <div class="chart-box mx-auto" style="max-width:800px;height:340px;">
                    <canvas id="${incId}"></canvas>
                </div>
                <p class="text-xs text-gray-500 mt-3 text-center">Click to remove</p>
                `;
                screenCard.onclick = () => moveToSuggested(c.id);

                const pdfCard = document.createElement("div");
                pdfCard.className = "bg-white shadow-md rounded p-6";
                pdfCard.innerHTML = `
                <h3 class="text-lg font-semibold text-gray-800 mb-4 text-center">${escHtml(c.title)}</h3>
                <div class="chart-box mx-auto" style="max-width:800px;height:340px;">
                    <canvas id="${pdfId}"></canvas>
                </div>
                `;

                wrap.appendChild(screenCard);
                pdfWrap.appendChild(pdfCard);

                draw(incId, c);
                draw(pdfId, c);

            });
        }


        function renderSuggested() {
            const wrap = document.getElementById("suggestedCharts");
            wrap.innerHTML = "";
            suggested.forEach(s => {
            const btn = document.createElement("button");
            btn.className = "w-full text-left border rounded p-3 hover:bg-gray-50 flex items-center justify-between";
            btn.innerHTML = `<span class="text-gray-800">${escHtml(s.title)}</span><span class="text-sm text-indigo-600">Add</span>`;
            btn.addEventListener("click", () => addSuggestion(s.id));
            wrap.appendChild(btn);
            });
        }

        function addSuggestion(id) {
            const idx = suggested.findIndex(s => s.id === id);
            if (idx === -1) return;
            const s = suggested[idx];
            if (!s.spec) {
                alert("This suggestion has no spec to rebuild the chart.");
                return;
            }

            const built = buildChart(s.spec);

            suggested.splice(idx, 1);
            included.unshift(built);

            renderSuggested();
            renderIncluded();
        }

        function moveToSuggested(chartId) {
            const idx = included.findIndex(c => c.id === chartId);
            if (idx === -1) return;
            const c = included[idx];
            included.splice(idx, 1);

            const spec = c.spec ? c.spec : null;

            const sid = (crypto.randomUUID ? crypto.randomUUID() : "s_" + Date.now() + Math.random());

            suggested.unshift({
                id: sid,
                title: c.title,
                spec: spec
            });

            renderIncluded();
            renderSuggested();
        }

        document.getElementById("addChartBtn").addEventListener("click", () => {
            try {
            const spec = {
                type: document.getElementById("chartType").value,
                x: document.getElementById("xCol").value,
                y: document.getElementById("yCol").value || null,
                agg: document.getElementById("agg").value
            };
            const built = buildChart(spec);
            included.unshift(built);
            renderIncluded();
            document.getElementById("includedCharts").scrollIntoView({behavior:"smooth"});
            } catch (e) {
            alert(e.message || String(e));
            }
        });

        function escHtml(s){ return String(s).replace(/[&<>"']/g,m=>({ "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;" }[m])); }
        function escAttr(s){ return String(s).replace(/"/g,"&quot;"); }
        function cap(s){ return s ? s[0].toUpperCase()+s.slice(1) : s; }
        function roundNice(n){ if(!Number.isFinite(n)) return 0; const a=Math.abs(n); if(a>=100) return Math.round(n); if(a>=10) return Math.round(n*10)/10; return Math.round(n*100)/100; }

        renderSuggested();
        renderIncluded();

    </script>

</x-app-layout>
