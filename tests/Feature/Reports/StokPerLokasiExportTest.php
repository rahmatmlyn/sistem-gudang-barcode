<?php

use App\Enums\LocationType;
use App\Reports\KartuStok;
use App\Reports\ReportExporter;
use App\Reports\StokPerLokasi;
use Illuminate\Support\Carbon;
use OpenSpout\Reader\XLSX\Reader;
use Tests\Support\Gudang;

beforeEach(function () {
    $this->g = Gudang::siapkan();
    $zona = $this->g->lokasi('Z', LocationType::Zona);
    $this->binZ = $this->g->lokasi('Z-01-01', LocationType::Bin, $zona);
    $this->zona = $zona;
    $this->gula = $this->g->barang(['sku' => 'SKU-GULA', 'name' => 'Gula Pasir']);
    $this->teh = $this->g->barang(['sku' => 'SKU-TEH', 'name' => 'Teh Celup']);
    $this->g->stokAwal($this->gula, $this->binZ, 12);
    $this->g->stokAwal($this->teh, $this->g->binA, 5);
});

/** @return list<list<mixed>> */
function bacaXlsx(string $isi): array
{
    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($path, $isi);
    $reader = new Reader;
    $reader->open($path);
    $rows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }
    $reader->close();

    return $rows;
}

it('stok per lokasi tersaring per zona', function () {
    $laporan = new StokPerLokasi(zonaId: $this->zona->id);

    expect($laporan->query()->get()->map(fn ($row) => [$row->location_code, $row->sku, (int) $row->qty])->all())
        ->toBe([['Z-01-01', 'SKU-GULA', 12]]);
});

it('export xlsx dan pdf berisi baris sesuai filter (UAT-21)', function () {
    $laporan = new StokPerLokasi(zonaId: $this->zona->id);

    $xlsx = bacaXlsx(ReportExporter::xlsx($laporan->headings(), $laporan->exportRows()));
    $pdf = ReportExporter::pdf('Stok per Lokasi', $laporan->filterDescription(), $laporan->headings(), $laporan->exportRows());

    expect($xlsx[0])->toBe($laporan->headings())
        ->and(array_slice($xlsx, 1))->toHaveCount(1)
        ->and($xlsx[1])->toContain('SKU-GULA')->not->toContain('SKU-TEH');
    expect($pdf)->toStartWith('%PDF');
});

it('export kartu stok mengikuti filter tanggal', function () {
    $kartu = new KartuStok($this->gula->id, null, Carbon::now()->subDay(), Carbon::now()->addDay());

    $xlsx = bacaXlsx(ReportExporter::xlsx($kartu->headings(), $kartu->exportRows()));

    expect($xlsx)->toHaveCount(4)
        ->and($xlsx[1][0])->toBe('Saldo awal')
        ->and(end($xlsx)[0])->toBe('Saldo akhir')
        ->and(end($xlsx))->toContain(12);
});
