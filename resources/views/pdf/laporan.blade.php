<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>{{ $title }}</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; }
    h1 { font-size: 15px; margin: 0; color: #1B5FD0; }
    .sub { color: #555; margin: 2px 0 10px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #bbb; padding: 4px 5px; text-align: left; }
    th { background: #eef3fc; }
    td.num { text-align: right; }
</style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="sub">{{ $subtitle }} · dicetak {{ now()->format('d/m/Y H:i') }}</div>
    <table>
        <thead><tr>@foreach ($headings as $heading)<th>{{ $heading }}</th>@endforeach</tr></thead>
        <tbody>
        @foreach ($rows as $row)
            <tr>@foreach ($row as $cell)<td @class(['num' => is_int($cell)])>{{ is_int($cell) ? number_format($cell, 0, ',', '.') : $cell }}</td>@endforeach</tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
