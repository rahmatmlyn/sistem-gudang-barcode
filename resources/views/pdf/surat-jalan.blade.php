<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Surat Jalan {{ $document->number }}</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
    h1 { font-size: 18px; margin: 0 0 4px; color: #1B5FD0; }
    .meta td { padding: 2px 12px 2px 0; }
    table.items { width: 100%; border-collapse: collapse; margin-top: 16px; }
    table.items th, table.items td { border: 1px solid #999; padding: 6px; text-align: left; }
    table.items th { background: #eef3fc; }
    .num { text-align: right; }
    .ttd { width: 100%; margin-top: 48px; }
    .ttd td { width: 33%; text-align: center; padding-top: 56px; }
</style>
</head>
<body>
    <h1>SURAT JALAN</h1>
    <div>{{ $document->warehouse->name }}</div>
    <table class="meta" style="margin-top: 12px">
        <tr><td>Nomor</td><td><strong>{{ $document->number }}</strong></td><td>Tanggal</td><td>{{ ($document->completed_at ?? now())->format('d/m/Y') }}</td></tr>
        <tr><td>Kepada</td><td>{{ $document->partner ?? '-' }}</td><td>No. pesanan</td><td>{{ $document->reference ?? '-' }}</td></tr>
    </table>

    <table class="items">
        <thead><tr><th>No.</th><th>SKU</th><th>Nama barang</th><th class="num">Qty</th><th>Satuan</th></tr></thead>
        <tbody>
        @foreach ($document->lines->groupBy('item_id')->values() as $index => $lines)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $lines->first()->item->sku }}</td>
                <td>{{ $lines->first()->item->name }}</td>
                <td class="num">{{ number_format($lines->sum('qty_packed'), 0, ',', '.') }}</td>
                <td>{{ $lines->first()->item->base_unit }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="ttd">
        <tr><td>Disiapkan oleh<br><br><br>{{ $document->completer?->name ?? '..................' }}</td><td>Pengemudi<br><br><br>..................</td><td>Penerima<br><br><br>..................</td></tr>
    </table>
</body>
</html>
