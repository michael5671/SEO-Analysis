<!doctype html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kết quả: {{ $run->query }}</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <h2>Kết quả cho: "{{ $run->query }}" (mode: {{ $run->mode }})</h2>
    <a href="{{ route('form') }}">← Phân tích câu khác</a>

    <p style="margin:10px 0 14px; display:flex; gap:12px; flex-wrap:wrap">
        <a href="{{ route('relevance.compute', ['runId' => $run->id]) }}"
            style="background:#111;color:#fff;padding:8px 12px;border-radius:8px;text-decoration:none">
            ⚙️ Tính điểm liên quan (AI)
        </a>
        <a href="{{ route('relevance.show', ['runId' => $run->id]) }}"
            style="background:#f3f4f6;color:#111;padding:8px 12px;border-radius:8px;text-decoration:none;border:1px solid #e5e7eb">
            🔎 Xem bảng liên quan (heatmap)
        </a>
    </p>


    <div style="display:flex;gap:40px;flex-wrap:wrap;margin-top:20px">
        <div style="width:380px">
            <h3>PAA → Subheadings</h3>
            <canvas id="paaConv"></canvas>
            <p>
                Tổng PAA: <b>{{ $paaConv->total_paa ?? 0 }}</b> —
                Đã match: <b>{{ $paaConv->matched_paa ?? 0 }}</b> —
                Tỷ lệ: <b>{{ $paaConv->pct_paa_to_heading ?? 0 }}%</b>
            </p>
        </div>

        <div style="width:380px">
            <h3>Subheadings → FAQ</h3>
            <canvas id="hdConv"></canvas>
            <p>
                Tổng H2/H3: <b>{{ $hdConv->total_headings ?? 0 }}</b> —
                Đã match: <b>{{ $hdConv->matched_headings ?? 0 }}</b> —
                Tỷ lệ: <b>{{ $hdConv->pct_heading_to_faq ?? 0 }}%</b>
            </p>
        </div>

        <div style="width:480px">
            <h3>PAA theo Intent</h3>
            <canvas id="intentChart"></canvas>
        </div>

        <div style="width:640px">
            <h3>Top URL có nhiều subheading</h3>
            <canvas id="perUrlChart"></canvas>
        </div>

        <div style="width:720px">
            <h3>Top match (AI): PAA ↔ Sản phẩm</h3>
            @if(($topRel ?? collect())->isEmpty())
                <p>Chưa có dữ liệu AI. Nhấn nút <b>“Tính điểm liên quan (AI)”</b> phía trên để tạo.</p>
            @else
                <table style="width:100%;border-collapse:collapse;font-size:14px">
                    <thead>
                        <tr style="text-align:left;border-bottom:1px solid #e5e7eb">
                            <th style="padding:8px">Câu hỏi (PAA)</th>
                            <th style="padding:8px">Sản phẩm/Dịch vụ</th>
                            <th style="padding:8px;width:100px">Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($topRel as $row)
                            <tr style="border-bottom:1px solid #f1f5f9">
                                <td style="padding:8px">{{ Str::limit($row->paa_question, 100) }}</td>
                                <td style="padding:8px">{{ $row->product_name }}</td>
                                <td style="padding:8px"><b>{{ number_format($row->score, 2) }}</b></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p style="margin-top:8px">
                    <a href="{{ route('relevance.show', ['runId' => $run->id]) }}">Mở heatmap chi tiết →</a>
                </p>
            @endif
        </div>


    </div>

    <script>
        const pct1 = {{ $paaConv->pct_paa_to_heading ?? 0 }};
        new Chart(document.getElementById('paaConv'), {
            type: 'bar',
            data: { labels: ['Tỷ lệ %'], datasets: [{ label: '% PAA → H2/H3', data: [pct1] }] },
            options: {
                plugins: { legend: { labels: { font: { weight: 'bold' } } } },
                scales: { y: { beginAtZero: true, max: 100, ticks: { font: { weight: 'bold' } } } }
            }
        });

        const pct2 = {{ $hdConv->pct_heading_to_faq ?? 0 }};
        new Chart(document.getElementById('hdConv'), {
            type: 'bar',
            data: { labels: ['Tỷ lệ %'], datasets: [{ label: '% H2/H3 → FAQ', data: [pct2] }] },
            options: {
                plugins: { legend: { labels: { font: { weight: 'bold' } } } },
                scales: { y: { beginAtZero: true, max: 100, ticks: { font: { weight: 'bold' } } } }
            }
        });

        // === Intent chart (dịch sang tiếng Việt) ===
        const intentMap = {
            'informational': 'Thông tin',
            'navigational': 'Điều hướng',
            'transactional': 'Giao dịch'
        };
        const intents = @json($intent);
        const labelsVi = Object.keys(intents).map(k => intentMap[k] ?? k);
        new Chart(document.getElementById('intentChart'), {
            type: 'bar',
            data: {
                labels: labelsVi,
                datasets: [{ label: 'Số câu hỏi', data: Object.values(intents) }]
            },
            options: {
                plugins: { legend: { labels: { font: { weight: 'bold' } } } },
                scales: {
                    y: { beginAtZero: true, ticks: { font: { weight: 'bold' } } },
                    x: { ticks: { font: { weight: 'bold' } } }
                }
            }
        });

        const perUrl = @json($perUrl);
        new Chart(document.getElementById('perUrlChart'), {
            type: 'bar',
            data: {
                labels: perUrl.map(x => x.url.length > 60 ? x.url.slice(0, 57) + '…' : x.url),
                datasets: [{ label: '#H2/H3', data: perUrl.map(x => x.c) }]
            },
            options: {
                indexAxis: 'y',
                plugins: { legend: { labels: { font: { weight: 'bold' } } } },
                scales: {
                    x: { beginAtZero: true, ticks: { font: { weight: 'bold' } } },
                    y: { ticks: { font: { weight: 'bold' } } }
                }
            }
        });

    </script>

</body>

</html>