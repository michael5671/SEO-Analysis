<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SEO Demo</title>
</head>
<body>
  <h2>SEO Demo — PAA → H2/H3 → FAQ</h2>
  <form method="post" action="{{ route('analyze') }}">
    @csrf
    <input name="q" placeholder="Nhập domain / tên chủ thể / free text" style="width:480px" />
    <button type="submit">Phân tích</button>
  </form>
  @if ($errors->any()) <p style="color:red">{{ $errors->first() }}</p> @endif
</body>
</html>
