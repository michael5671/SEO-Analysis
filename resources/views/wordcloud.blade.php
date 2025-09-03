<!doctype html>
<html lang="vi">

<head>
  <meta charset="utf-8">
  <title>Word Cloud — Run #{{ $run->id }}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    body {
      font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      background: #f7fafc;
      margin: 0;
      padding: 0;
    }

    .wrap {
      max-width: 1100px;
      margin: 24px auto;
      background: #fff;
      padding: 16px 20px;
      border-radius: 14px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
    }

    #tooltip {
      position: absolute;
      padding: 8px 10px;
      background: rgba(0, 0, 0, 0.85);
      color: #fff;
      border-radius: 8px;
      pointer-events: none;
      font-size: 12px;
      opacity: 0;
      transform: translate(-50%, -120%);
      white-space: nowrap;
    }

    .subtitle {
      color: #4a5568;
      margin-top: 4px;
    }
  </style>
</head>

<body>
  <div class="wrap">
    <h2>Word Cloud PAA — Run #{{ $run->id }}</h2>
    <p class="subtitle">Kích thước chữ ∝ độ phổ biến + đại diện</p>

    <p style="margin:12px 0">
      <a href="{{ route('runs.show', ['id' => $run->id]) }}" style="display:inline-block;padding:8px 14px;background:#111;color:#fff;
              border-radius:8px;text-decoration:none;font-size:14px">
        ← Quay về Dashboard
      </a>
    </p>
    <div id="cloud"></div>
  </div>

  <div id="tooltip"></div>

  <script src="https://d3js.org/d3.v7.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/d3-cloud/build/d3.layout.cloud.min.js"></script>
  <script>
    const W = Math.min(1100, window.innerWidth - 48), H = 600;
    const svg = d3.select('#cloud').append('svg').attr('width', W).attr('height', H);
    const g = svg.append('g').attr('transform', `translate(${W / 2},${H / 2})`);
    const tooltip = d3.select('#tooltip');

    function colorFor(text) {
      let h = 0; for (let i = 0; i < text.length; i++) h = (h * 31 + text.charCodeAt(i)) >>> 0;
      return `hsl(${h % 360} 60% 45%)`;
    }

    fetch(`{{ route('wordcloud.data', ['id' => $run->id]) }}`)
      .then(r => r.json())
      .then(data => {
        d3.layout.cloud()
          .size([W, H])
          .words(data.map(d => ({ text: d.text, size: d.value })))
          .padding(3)
          .rotate(() => 0)
          .font('Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif')
          .fontSize(d => d.size)
          .on('end', draw)
          .start();

        function draw(words) {
          g.selectAll('text')
            .data(words)
            .enter().append('text')
            .style('font-family', 'Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif')
            .style('font-size', d => d.size + 'px')
            .style('fill', d => colorFor(d.text))
            .attr('text-anchor', 'middle')
            .attr('transform', d => `translate(${[d.x, d.y]})rotate(${d.rotate})`)
            .text(d => d.text)
            .on('mousemove', (evt, d) => {
              tooltip.style('left', evt.pageX + 'px').style('top', evt.pageY + 'px')
                .style('opacity', 1)
                .html(`${d.text}<br><small>size: ${d.size}</small>`);
            })
            .on('mouseleave', () => tooltip.style('opacity', 0))
            .on('click', (evt, d) => {
              alert(`Bạn vừa click: ${d.text}`);
              // Hoặc mở modal/redirect sang danh sách câu hỏi PAA chứa cụm từ này
            });
        }
      });
  </script>
</body>

</html>