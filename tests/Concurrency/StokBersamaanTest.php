<?php

use App\Enums\DocumentType;
use App\Models\DocumentLine;
use App\Services\StockService;
use Tests\Support\Bersamaan;
use Tests\Support\Gudang;

/*
 * Proses OS sungguhan (bukan RefreshDatabase): data harus ter-commit agar terlihat oleh proses lain.
 * Suite ini memakai DatabaseTruncation (lihat tests/Pest.php).
 */

beforeEach(function () {
    $this->g = Gudang::siapkan();
    $this->stok = app(StockService::class);
});

it('10 proses reserve stok tersisa 1: tepat satu berhasil (UAT-26)', function () {
    $barang = $this->g->barang();
    $this->g->stokAwal($barang, $this->g->binA, 1);
    $baris = $this->g->dokumen(DocumentType::OUT, [['item' => $barang, 'from' => $this->g->binA]])->lines->first();

    $hasil = Bersamaan::jalankan(array_fill(0, 10, ['reserve', $baris->id, 1, 1]));

    expect(array_count_values($hasil))->toEqual(['OK' => 1, 'STOK_KURANG' => 9]);
    expect($baris->fresh()->qty_scanned)->toBe(1);
    expect($this->stok->available($barang->id, $this->g->binA->id))->toBe(0);
    expect(Gudang::saldo($barang, $this->g->binA))->toBe(1);
});

it('10 proses x 20 reserve dari 10 dokumen: total cocok lalu ledger cocok setelah dikirim (UAT-27)', function () {
    $barang = $this->g->barang();
    $this->g->stokAwal($barang, $this->g->binA, 150);
    $daftarBaris = collect(range(1, 10))->map(fn () => $this->g->dokumen(DocumentType::OUT, [
        ['item' => $barang, 'from' => $this->g->binA, 'planned' => 20],
    ])->lines->first());

    $hasil = Bersamaan::jalankan($daftarBaris->map(fn (DocumentLine $baris) => ['reserve', $baris->id, 1, 20])->all());

    expect(array_count_values($hasil))->toEqual(['OK' => 150, 'STOK_KURANG' => 50]);
    expect((int) DocumentLine::whereIn('id', $daftarBaris->pluck('id'))->sum('qty_scanned'))->toBe(150);
    expect($this->stok->available($barang->id, $this->g->binA->id))->toBe(0);

    foreach ($daftarBaris as $baris) {
        $baris->refresh();
        if ($baris->qty_scanned === 0) {
            continue;
        }
        $baris->update(['qty_planned' => $baris->qty_scanned, 'qty_packed' => $baris->qty_scanned]);
        $this->stok->issue($baris->document, $this->g->staf);
    }

    expect(Gudang::saldo($barang, $this->g->binA))->toBe(0);
    Gudang::assertLedgerCocok();
});

it('dokumen dengan pasangan item-lokasi terbalik selesai bersamaan tanpa deadlock', function () {
    [$barangA, $barangB] = [$this->g->barang(), $this->g->barang()];
    $this->g->stokAwal($barangA, $this->g->binA, 100);
    $this->g->stokAwal($barangB, $this->g->binA, 100);

    $dokumen = collect(range(0, 9))->map(function (int $urutan) use ($barangA, $barangB) {
        $pasangan = $urutan % 2 === 0 ? [$barangA, $barangB] : [$barangB, $barangA];

        return $this->g->dokumen(DocumentType::TRF, array_map(fn ($barang) => [
            'item' => $barang, 'from' => $this->g->binA, 'to' => $this->g->binB, 'scanned' => 5,
        ], $pasangan));
    });

    $hasil = Bersamaan::jalankan($dokumen->map(fn ($trf) => ['transfer', $trf->id, $this->g->staf->id])->all());

    expect($hasil)->toBe(array_fill(0, 10, 'OK'));
    foreach ([$barangA, $barangB] as $barang) {
        expect(Gudang::saldo($barang, $this->g->binA))->toBe(50);
        expect(Gudang::saldo($barang, $this->g->binB))->toBe(50);
    }
    Gudang::assertLedgerCocok();
});

it('nomor dokumen tetap unik saat dibuat bersamaan', function () {
    $hasil = Bersamaan::jalankan(array_fill(0, 10, ['nomor', 'GRN', 10]));

    $nomor = array_map(fn (string $baris) => substr($baris, 3), $hasil);
    $tahun = now()->year;
    expect($nomor)->toHaveCount(100);
    expect(array_unique($nomor))->toHaveCount(100);
    expect(collect($nomor)->sort()->values()->all())
        ->toBe(array_map(fn (int $urutan) => sprintf('GRN-%d-%05d', $tahun, $urutan), range(1, 100)));
});
