<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<style>
    @page { size: 50mm 30mm; margin: 0; }
    body { margin: 0; font-family: Helvetica, sans-serif; color: #000; } /* font bawaan PDF: tanpa embedding, hemat memori */
    .label { width: 46mm; height: 27mm; padding: 1.5mm 2mm; overflow: hidden; page-break-after: always; text-align: center; }
    .label:last-child { page-break-after: auto; }
    .judul { font-size: 7.5pt; font-weight: bold; line-height: 1.1; height: 3.4mm; overflow: hidden; white-space: nowrap; }
    .ket { font-size: 5.5pt; line-height: 1.1; height: 2.6mm; overflow: hidden; white-space: nowrap; }
    .linear { width: 44mm; height: 14mm; margin-top: 0.8mm; }
    .ean { width: 38mm; } /* quiet zone EAN-13 ≥ 11 modul di kiri-kanan */
    .qr { width: 17mm; height: 17mm; margin-top: 0.4mm; }
    .kode { font-size: 7pt; letter-spacing: 0.3pt; margin-top: 0.4mm; }
    .lokasi .judul { font-size: 12pt; height: 5mm; }
</style>
</head>
<body>
@foreach ($labels as $label)
    <div @class(['label', 'lokasi' => str_starts_with($label['kode'], 'LOC-')])>
        <div class="judul">{{ $label['judul'] }}</div>
        <div class="ket">{{ $label['keterangan'] }}</div>
        <img src="{{ $label['gambar'] }}" class="{{ match ($label['jenis']) { 'QR' => 'qr', 'EAN-13' => 'linear ean', default => 'linear' } }}" alt="{{ $label['kode'] }}">
        <div class="kode">{{ $label['kode'] }}</div>
    </div>
@endforeach
</body>
</html>
