<?php

use App\Enums\DocumentType;
use App\Reports\KartuStok;
use App\Services\StockService;
use Illuminate\Support\Carbon;
use Tests\Support\Gudang;

beforeEach(function () {
    $this->g = Gudang::siapkan();
    $this->barang = $this->g->barang();
    $this->stok = app(StockService::class);
});

function pada(string $waktu, Closure $aksi): void
{
    Carbon::setTestNow($waktu);
    $aksi();
    Carbon::setTestNow();
}

/** 1 Nov: terima 100 ke binA; 5 Nov: kirim 30 dari binA; 10 Nov: pindah 20 binA→binB; 15 Nov: terima 7 ke binB. */
function riwayatStok($test): void
{
    $g = $test->g;
    pada('2026-11-01 08:00', fn () => $g->stokAwal($test->barang, $g->binA, 100));
    pada('2026-11-05 09:00', function () use ($g, $test) {
        $out = $g->dokumen(DocumentType::OUT, [['item' => $test->barang, 'from' => $g->binA, 'planned' => 30, 'scanned' => 30, 'packed' => 30]]);
        $test->stok->issue($out, $g->staf);
    });
    pada('2026-11-10 10:00', function () use ($g, $test) {
        $trf = $g->dokumen(DocumentType::TRF, [['item' => $test->barang, 'from' => $g->binA, 'to' => $g->binB, 'scanned' => 20]]);
        $test->stok->transfer($trf, $g->staf);
    });
    pada('2026-11-15 11:00', fn () => $g->stokAwal($test->barang, $g->binB, 7));
}

it('saldo akhir = saldo awal + masuk − keluar (UAT-20)', function () {
    riwayatStok($this);

    $kartu = new KartuStok($this->barang->id, null, Carbon::parse('2026-11-03'), Carbon::parse('2026-11-12'));
    $baris = $kartu->rows();

    expect($kartu->saldoAwal())->toBe(100)
        ->and($baris)->toHaveCount(2)
        ->and($baris->sum('masuk'))->toBe(20)
        ->and($baris->sum('keluar'))->toBe(50)
        ->and($kartu->saldoAkhir())->toBe(100 + 20 - 50);
});

it('saldo berjalan per baris dan saldo akhir sama dengan stock_balances', function () {
    riwayatStok($this);

    $kartu = new KartuStok($this->barang->id, null, Carbon::parse('2026-10-01'), Carbon::parse('2026-11-30'));

    expect($kartu->rows()->pluck('saldo')->all())->toBe([100, 70, 70, 77])
        ->and($kartu->saldoAkhir())->toBe(Gudang::saldo($this->barang, $this->g->binA) + Gudang::saldo($this->barang, $this->g->binB));
});

it('filter lokasi menghitung transfer keluar dari lokasi itu', function () {
    riwayatStok($this);

    $kartu = new KartuStok($this->barang->id, $this->g->binA->id, Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30'));

    expect($kartu->rows()->map(fn ($baris) => [$baris->masuk, $baris->keluar, $baris->saldo])->all())
        ->toBe([[100, 0, 100], [0, 30, 70], [0, 20, 50]])
        ->and($kartu->saldoAkhir())->toBe(Gudang::saldo($this->barang, $this->g->binA));
});
