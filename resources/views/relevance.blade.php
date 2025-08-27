<!doctype html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <title>Relevance — Run #{{ $run->id }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-chart-matrix@2.0.1/dist/chartjs-chart-matrix.min.js"></script>

    <style>
        body {
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th,
        td {
            padding: 8px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            vertical-align: top;
        }

        .btn {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 8px;
            text-decoration: none;
        }

        .btn-dark {
            background: #111;
            color: #fff;
        }

        .btn-light {
            background: #f3f4f6;
            color: #111;
            border: 1px solid #e5e7eb;
        }

        .hm-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(520px, 1fr));
            gap: 18px;
            align-items: start;
        }

        .hm-card {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 12px 14px;
            background: #fff;
        }

        .hm-title {
            font-weight: 700;
            font-size: 14px;
            margin: 0 0 8px 0;
            color: #374151;
        }

        .hm-canvas {
            width: 100%;
            height: 220px;
        }
    </style>
</head>

<body>
    @php use Illuminate\Support\Str; @endphp

    <h2>Điểm liên quan PAA ↔ Sản phẩm (Run #{{ $run->id }})</h2>
    <p style="margin:10px 0 14px; display:flex; gap:12px; flex-wrap:wrap">
        <a class="btn btn-dark" href="{{ route('relevance.compute', ['runId' => $run->id]) }}">⚙️ Tính lại điểm liên
            quan
            (AI)</a>
        <a class="btn btn-light" href="{{ route('runs.show', ['id' => $run->id]) }}">← Về dashboard</a>
    </p>
    <button id="sortAsc">Sort ↑ (nhỏ → lớn)</button>
    <button id="sortDesc">Sort ↓ (lớn → nhỏ)</button>



    <canvas id="heatmap" width="1200" height="600"></canvas>

    <hr style="margin:18px 0">

    <h3>Chi tiết (Top theo từng câu hỏi)</h3>
    <table>
        <thead>
            <tr>
                <th style="width:45%">PAA</th>
                <th style="width:35%">Sản phẩm/Dịch vụ</th>
                <th style="width:10%">Score</th>
            </tr>
        </thead>
        <tbody>
            @foreach($matrix as $qid => $row)
                @php
                    $qText = $questions[$qid] ?? '';
                    $sorted = collect($row)->sortDesc()->take(5); // top 5 sản phẩm cho câu này
                @endphp
                @foreach($sorted as $pid => $score)
                    <tr>
                        @if ($loop->first)
                            <td rowspan="{{ $sorted->count() }}"><b>{{ $qText }}</b></td>
                        @endif
                        <td>{{ $products[$pid] ?? $pid }}</td>
                        <td><b>{{ number_format($score, 2) }}</b></td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

    <script>
        // ====== DATA ======
        const qLabels = @json(array_values($questions->toArray()));   // hàng
        const pIds = @json(array_keys($products->toArray()));      // id cột
        const pLabels = @json(array_values($products->toArray()));    // nhãn cột
        const matrix = @json($matrix);                               // {qid:{pid:score,...},...}

        const qKeys = Object.keys(matrix);
        const qi = {}; qKeys.forEach((k, i) => qi[k] = i);
        const pKeys = pIds;
        const pi = {}; pKeys.forEach((k, j) => pi[k] = j);

        const cells = [];
        let vmin = 1, vmax = 0;
        qKeys.forEach((qid) => {
            pKeys.forEach((pid) => {
                const v = +(matrix[qid]?.[pid] ?? 0);
                // DÙNG NHÃN CHUỖI cho category scale
                cells.push({
                    x: (pLabels[pi[pid]] ?? String(pid)),
                    y: (qLabels[qi[qid]] ?? String(qid)),
                    v
                });
                if (v < vmin) vmin = v;
                if (v > vmax) vmax = v;
            });
        });

        // ====== PALETTE: thấp -> nhạt, cao -> đậm (blue scale) ======
        const stops = [
            [0.00, [230, 244, 255]],
            [0.25, [189, 215, 245]],
            [0.50, [120, 170, 225]],
            [0.75, [60, 120, 190]],
            [1.00, [15, 60, 120]],
        ];
        function lerp(a, b, t) { return a + (b - a) * t; }
        function interpColor(t) {
            t = Math.max(0, Math.min(1, t));
            for (let i = 0; i < stops.length - 1; i++) {
                const [t0, c0] = stops[i], [t1, c1] = stops[i + 1];
                if (t >= t0 && t <= t1) {
                    const k = (t - t0) / (t1 - t0);
                    const r = Math.round(lerp(c0[0], c1[0], k));
                    const g = Math.round(lerp(c0[1], c1[1], k));
                    const b = Math.round(lerp(c0[2], c1[2], k));
                    return `rgb(${r},${g},${b})`;
                }
            }
            const c = stops.at(-1)[1]; return `rgb(${c[0]},${c[1]},${c[2]})`;
        }
        function colorFor(v) {
            if (vmax <= vmin) return interpColor(0);
            const t = (v - vmin) / (vmax - vmin);
            return interpColor(t);
        }

        // ====== Colorbar (đảo chiều đúng: dưới thấp, trên cao) ======
        const colorbar = {
            id: 'colorbar',
            afterDraw(chart) {
                const { ctx, chartArea: area } = chart;
                if (!area) return;
                const w = 16, gap = 12;
                const x0 = area.right + gap;
                const y0 = area.top;
                const h = area.bottom - area.top;

                // Gradient từ DƯỚI (thấp/nhạt) -> TRÊN (cao/đậm)
                const grad = ctx.createLinearGradient(0, y0 + h, 0, y0);
                stops.forEach(s => grad.addColorStop(s[0], `rgb(${s[1][0]},${s[1][1]},${s[1][2]})`));
                ctx.fillStyle = grad;
                ctx.fillRect(x0, y0, w, h);
                ctx.strokeStyle = '#bdbdbd';
                ctx.strokeRect(x0, y0, w, h);

                // Ticks: đáy = vmin, giữa = mid, đỉnh = vmax
                ctx.fillStyle = '#333';
                ctx.font = '12px system-ui, sans-serif';
                const ticks = [vmin, (vmin + vmax) / 2, vmax];
                const ys = [y0 + h, y0 + h / 2, y0];
                ticks.forEach((val, i) => {
                    const label = (val * 100).toFixed(0) + '%';
                    ctx.fillText(label, x0 + w + 8, ys[i] + (i === 0 ? 0 : 4));
                });
                ctx.font = '600 12px system-ui, sans-serif';
                // ctx.fillText('Score', x0 + w + 8, y0 - 8);
            }
        };

        // Tránh lỗi chartArea undefined lần đầu
        function cw(chart) { const a = chart.chartArea; if (!a) return 0; return (a.width / Math.max(1, pKeys.length)) * 1.0; }
        function ch(chart) { const a = chart.chartArea; if (!a) return 0; return (a.height / Math.max(1, qKeys.length)) * 1.0; }

        // ====== Wrap nhãn X để hiện hết tên dịch vụ ======
        function wrapLabel(lbl, maxLen = 25) {
            if (!lbl) return '';
            const words = String(lbl).split(' ');
            const lines = [];
            let cur = '';
            for (const w of words) {
                if ((cur ? cur + ' ' : '').length + w.length > maxLen) {
                    if (cur) lines.push(cur);
                    cur = w;
                } else {
                    cur = (cur ? cur + ' ' : '') + w;
                }
            }
            if (cur) lines.push(cur);
            return lines; // Chart.js chấp nhận array => multi-line
        }

        // ====== Main chart =======
        // Tăng padding dưới để nhãn nhiều dòng không bị cắt
        const bottomPad = 60; // cần hơn? tăng tới 80–100
        // Chiều cao theo số hàng
        const rowH = 28;
        const canvas = document.getElementById('heatmap');
        canvas.height = Math.max(380, qKeys.length * rowH + 100);
        let xLabelsCurrent = [...pLabels];

        const chart = new Chart(canvas, {
            type: 'matrix',
            plugins: [colorbar],
            data: {
                datasets: [{
                    label: 'Relevance',
                    data: cells,
                    width: (ctx) => cw(ctx.chart),
                    height: (ctx) => ch(ctx.chart),
                    backgroundColor: (ctx) => colorFor(ctx.raw.v ?? 0),
                    borderColor: '#fff',
                    borderWidth: 1
                }]
            },
            options: {
                layout: { padding: { right: 150, bottom: 210 } },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            title: (items) => {
                                const it = items[0];
                                return `${it.raw.x} ↔ ${it.raw.y}`;
                            },
                            label: (it) => `Score: ${(it.raw.v ?? 0).toFixed(3)}  (${((it.raw.v ?? 0) * 100).toFixed(1)}%)`
                        }
                    }
                },
                scales: {
                    x: {
                        type: 'category',
                        labels: xLabelsCurrent,
                        offset: true,
                        ticks: {
                            autoSkip: false,
                            // value = index -> trả về NHÃN
                            callback: (value /* idx */) => wrapLabel(xLabelsCurrent[value]),
                            maxRotation: 50,
                            minRotation: 50,
                            font: { weight: 'bold', size: 8 }
                        },
                        grid: { display: false }
                    },
                    y: {
                        type: 'category',
                        labels: qLabels,
                        ticks: { autoSkip: false, font: { weight: 'bold' } },
                        grid: { display: false }
                    }

                }
            }
        });
        //===== Ordering chart ======
        function buildCells(order /* array index của pKeys theo thứ tự mới */) {
            const cells = [];
            let vmin = 1, vmax = 0;
            qKeys.forEach(qid => {
                order.forEach(j => {
                    const pid = pKeys[j];
                    const v = +(matrix[qid]?.[pid] ?? 0);
                    cells.push({ x: pLabels[j], y: qLabels[qi[qid]], v });
                    if (v < vmin) vmin = v;
                    if (v > vmax) vmax = v;
                });
            });
            return { cells, vmin, vmax };
        }
        // sort theo điểm của 1 câu hỏi (qid) giảm dần
        function orderBySum(asc = false) {
            // Tính tổng điểm cho từng cột (sản phẩm)
            const sums = pKeys.map(pid => {
                let s = 0;
                qKeys.forEach(qid => s += +(matrix[qid]?.[pid] ?? 0));
                return s;
            });

            // Trả về mảng index [0..n-1] theo thứ tự
            return [...pKeys.keys()].sort((a, b) => {
                return asc ? sums[a] - sums[b] : sums[b] - sums[a];
            });
        }


        // giữ nguyên thứ tự gốc
        function orderOriginal() { return [...pKeys.keys()]; }

        function applyOrder(order) {
            // Nhãn cột theo thứ tự mới
            xLabelsCurrent = order.map(i => pLabels[i]);
            chart.options.scales.x.labels = xLabelsCurrent;
            chart.data.labels = xLabelsCurrent;

            // Build lại cells
            const cells = [];
            qKeys.forEach(qid => {
                order.forEach(j => {
                    const pid = pKeys[j];
                    const v = +(matrix[qid]?.[pid] ?? 0);
                    cells.push({ x: pLabels[j], y: qLabels[qi[qid]], v });
                });
            });
            chart.data.datasets[0].data = cells;

            chart.update();
        }
        //apply sort
        document.getElementById('sortAsc').addEventListener('click', () => {
            applyOrder(orderBySum(true));   // asc = true
        });
        document.getElementById('sortDesc').addEventListener('click', () => {
            applyOrder(orderBySum(false));  // asc = false
        });



    </script>
</body>

</html>