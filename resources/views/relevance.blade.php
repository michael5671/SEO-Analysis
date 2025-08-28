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
    </style>
</head>

<body>
    @php use Illuminate\Support\Str; @endphp

    <h2>Điểm liên quan PAA ↔ Sản phẩm (Run #{{ $run->id }})</h2>
    <p style="margin:10px 0 14px; display:flex; gap:12px; flex-wrap:wrap">
        <a class="btn btn-dark" href="{{ route('relevance.compute', ['runId' => $run->id]) }}">⚙️ Tính lại điểm liên
            quan
        </a>
        <a class="btn btn-light" href="{{ route('runs.show', ['id' => $run->id]) }}">← Về dashboard</a>
    </p>

    <h3 style="margin-top:22px">Heatmap </h3>
    <div id="heatmaps" style="display:grid; gap:14px"></div>

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
        // ====== DATA (như cũ) ======
        const qLabels = @json(array_values($questions->toArray()));
        const pIds = @json(array_keys($products->toArray()));
        const pLabels = @json(array_values($products->toArray()));
        const matrix = @json($matrix);

        const qKeys = Object.keys(matrix);
        const pKeys = pIds;

        (function () {
            // ---- Lấy min/max toàn cục để chuẩn hóa màu ----
            let vmin = Infinity, vmax = -Infinity;
            qKeys.forEach(qid => {
                const row = matrix[qid] || {};
                Object.values(row).forEach(v => {
                    if (typeof v === 'number') {
                        if (v < vmin) vmin = v;
                        if (v > vmax) vmax = v;
                    }
                });
            });
            if (!isFinite(vmin)) vmin = 0;
            if (!isFinite(vmax)) vmax = 1;
            if (vmax === vmin) vmax = vmin + 1e-6; // tránh chia 0

            // ---- Hàm map score -> màu (thang xanh -> vàng -> đỏ) ----
            function colorFor(v) {

                const t = Math.max(0, Math.min(1, (v - vmin) / (vmax - vmin)));
                // từ trắng (#FFFFFF) -> xanh đậm (#0D3B66)
                const r = Math.round(0xFF + t * (0x0D - 0xFF));
                const g = Math.round(0xFF + t * (0x3B - 0xFF));
                const b = Math.round(0xFF + t * (0x66 - 0xFF));
                return `rgb(${r},${g},${b})`;

            }

            // ---- Tạo heatmap cho từng câu ----
            const container = document.getElementById('heatmaps');

            // Responsive: mỗi chart là 1 "card" riêng, 100% rộng
            container.style.gridTemplateColumns = '1fr';

            // Tính cell size theo số product
            function computeCellSize(canvasWidth, nCols) {
                const desired = Math.floor(canvasWidth / Math.max(1, nCols)) - 4; // 4px gap
                const cellW = Math.max(12, Math.min(40, desired));
                const cellH = 24; // một hàng nhìn gọn
                return { cellW, cellH };
            }

            // Tạo legend đơn giản 1 hàng
            function renderLegend() {
                const wrap = document.createElement('div');
                wrap.style.display = 'flex';
                wrap.style.alignItems = 'center';
                wrap.style.gap = '8px';
                wrap.style.fontSize = '12px';
                wrap.style.margin = '8px 0 0';
                const bar = document.createElement('div');
                bar.style.width = '100%';
                bar.style.maxWidth = '600px';
                bar.style.height = '8px';
                bar.style.borderRadius = '4px';
                // gradient CSS từ vmin tới vmax
                bar.style.background = 'linear-gradient(to right, #FFFFFF, #0D3B66)';
                const minL = document.createElement('span'); minL.textContent = vmin.toFixed(2);
                const maxL = document.createElement('span'); maxL.textContent = vmax.toFixed(2);
                wrap.appendChild(minL); wrap.appendChild(bar); wrap.appendChild(maxL);
                return wrap;
            }

            qKeys.forEach((qid, idx) => {
                const row = matrix[qid] || {};
                // Chuẩn bị dữ liệu matrix (1 hàng -> y = 0)
                const data = pKeys.map((pid, j) => {
                    const v = Number(row[pid] ?? 0);
                    return { x: j, y: 0, v };
                });

                // Card
                const card = document.createElement('div');
                // Sort control
                const sortDiv = document.createElement('div');
                sortDiv.style.margin = '6px 0';
                const select = document.createElement('select');
                select.innerHTML = `
                <option value="none">-- Không sắp xếp --</option>
                <option value="desc">Score: Giảm dần</option>
                <option value="asc">Score: Tăng dần</option>
                `;
                sortDiv.appendChild(select);
                // Gắn sự kiện sort
                select.addEventListener('change', () => {
                    let row = matrix[qid] || {};
                    let entries = pKeys.map((pid, j) => {
                        return { pid, label: pLabels[j], score: Number(row[pid] ?? 0) };
                    });

                    if (select.value === 'desc') {
                        entries.sort((a, b) => b.score - a.score);
                    } else if (select.value === 'asc') {
                        entries.sort((a, b) => a.score - b.score);
                    } else {
                        entries = pKeys.map((pid, j) => ({ pid, label: pLabels[j], score: Number(row[pid] ?? 0) }));
                    }

                    // Cập nhật dữ liệu
                    chartInstance.data.datasets[0].data = entries.map((e, j) => ({
                        x: j, y: 0, v: e.score
                    }));
                    chartInstance.options.scales.x.labels = entries.map(e => e.label);

                    chartInstance.update();
                });
                card.appendChild(sortDiv);

                card.style.border = '1px solid #e5e7eb';
                card.style.borderRadius = '12px';
                card.style.padding = '12px';
                card.style.background = '#D8BB89';

                // Tiêu đề câu hỏi
                const h = document.createElement('div');
                h.style.fontWeight = '600';
                h.style.margin = '0 0 0px';
                h.textContent = qLabels[idx] || `PAA #${idx + 1}`;
                card.appendChild(h);

                // Canvas
                const canvas = document.createElement('canvas');
                canvas.style.width = '100%';
                canvas.style.display = 'block';
                // Chiều cao = cellH + padding cho trục
                const approxWidth = container.clientWidth || 800;
                const { cellW, cellH } = computeCellSize(approxWidth, pKeys.length);
                const height = cellH + 280; // thêm trục + padding
                canvas.height = height;
                card.appendChild(canvas);

                // Legend
                card.appendChild(renderLegend());

                container.appendChild(card);

                // Khởi tạo chart
                const ctx = canvas.getContext('2d');
                const chartInstance = new Chart(ctx, {
                    type: 'matrix',
                    data: {
                        datasets: [{
                            label: 'Relevance',
                            data,
                            // Cell size
                            width: ({ chart }) => computeCellSize(chart.width, pKeys.length).cellW,
                            height: ({ chart }) => computeCellSize(chart.width, pKeys.length).cellH,
                            backgroundColor: (ctx) => colorFor(ctx.raw.v),
                            borderWidth: 0, // bỏ viền ô
                            hoverBorderWidth: 1,
                            hoverBorderColor: '#111'
                        }]
                    },
                    options: {
                        responsive: true,

                        layout: { padding: { top: 0, right: 170, bottom: 80, left: 10 } },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    title: (items) => {
                                        const it = items[0];
                                        const j = it.raw.x;
                                        return pLabels[j] || `Product #${j + 1}`;
                                    },
                                    label: (it) => `Score: ${Number(it.raw.v).toFixed(2)}`
                                }
                            }
                        },
                        scales: {
                            x: {
                                type: 'category',
                                labels: pLabels,
                                offset: false,
                                grid: { display: false },
                                ticks: {
                                    autoSkip: false,
                                    maxRotation: 33,
                                    minRotation: 33,
                                    padding: 20,
                                    callback: function (val) {
                                        const s = this.getLabelForValue(val);
                                        // rút gọn nhãn dài cho gọn trục
                                        return s.length > 18 ? s.slice(0, 16) + '…' : s;
                                    },
                                    font: { size: 12, weight: '600' }
                                },
                                border: { display: false }
                            },
                            y: {
                                display: false,
                                type: 'category',
                                labels: [''],
                                grid: { display: false, drawTicks: false },
                                ticks: { display: false, padding: 20 },
                                border: { display: false }
                            }
                        },
                        onClick: (evt, els) => {
                            const el = els?.[0];
                            if (!el) return;
                            const j = el.element.$context.raw.x;
                            const pid = pKeys[j];
                            const productName = pLabels[j] ?? pid;
                            const score = Number(el.element.$context.raw.v).toFixed(2);
                            // Bạn có thể thay alert bằng điều hướng/hiển thị modal chi tiết
                            alert(`PAA: ${qLabels[idx]}\nProduct: ${productName}\nScore: ${score}`);
                        }
                    },
                    plugins: [{
                        id: 'matrixLabels',
                        afterDatasetsDraw(chart) {
                            const { ctx } = chart;
                            const meta = chart.getDatasetMeta(0);

                            meta.data.forEach((rect, i) => {
                                const val = chart.data.datasets[0].data[i].v;
                                if (val == null) return;
                                // Chartjs matrix rectangle element có .x, .y, .width, .height
                                const x = rect.x;
                                const y = rect.y;

                                // Lấy màu nền của ô
                                const bg = colorFor(val); // trả về "rgb(r,g,b)"
                                const rgb = bg.match(/\d+/g).map(Number);
                                const luminance = 0.2126 * rgb[0] + 0.7152 * rgb[1] + 0.0722 * rgb[2];

                                // Nếu nền sáng thì chữ đen, nền tối thì chữ trắng
                                const textColor = luminance > 140 ? '#111' : '#FFF';

                                ctx.save();
                                ctx.fillStyle = '#111';
                                ctx.font = '10px sans-serif';
                                ctx.textAlign = 'center';
                                ctx.textBaseline = 'middle';
                                ctx.fillText(Number(val).toFixed(2), x + 20, y + 15);
                                ctx.restore();
                            });
                        }
                    }]
                });
            });


            // Reflow khi resize để cell width luôn hợp lý
            let rAF;
            window.addEventListener('resize', () => {
                cancelAnimationFrame(rAF);
                rAF = requestAnimationFrame(() => {

                });
            });
        })();
    </script>
</body>

</html>