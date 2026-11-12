<?php

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Exceptions\DokumenTidakAktifException;
use App\Exceptions\MelebihiRencanaException;
use App\Exceptions\StokTidakCukupException;
use App\Models\DocumentLine;
use App\Models\ItemBarcode;
use App\Models\StockMovement;
use App\Services\StockService;
use Tests\Support\Gudang;

beforeEach(function () {
    $this->g = Gudang::siapkan();
    $this->stok = app(StockService::class);
    $this->barang = $this->g->barang();
});

function barisOut(Gudang $g, $barang, int $planned = 0): DocumentLine
{
    return $g->dokumen(DocumentType::OUT, [['item' => $barang, 'from' => $g->binA, 'planned' => $planned]])->lines->first();
}

it('reserve menaikkan qty_scanned tanpa mengubah saldo maupun ledger', function () {
    $this->g->stokAwal($this->barang, $this->g->binA, 5);
    $baris = barisOut($this->g, $this->barang);
    $jumlahMovement = StockMovement::count();

    $this->stok->reserve($baris, 3);

    expect($baris->fresh()->qty_scanned)->toBe(3);
    expect(Gudang::saldo($this->barang, $this->g->binA))->toBe(5);
    expect(StockMovement::count())->toBe($jumlahMovement);
    expect($this->stok->available($this->barang->id, $this->g->binA->id))->toBe(2);
});

it('reserve menolak bila tersedia kurang dan saldo tidak minus', function () {
    $this->g->stokAwal($this->barang, $this->g->binA, 2);
    $baris = barisOut($this->g, $this->barang);

    expect(fn () => $this->stok->reserve($baris, 3))->toThrow(StokTidakCukupException::class);

    expect($baris->fresh()->qty_scanned)->toBe(0);
    expect(Gudang::saldo($this->barang, $this->g->binA))->toBe(2);
});

it('reserve menolak bila belum ada saldo di lokasi', function () {
    $baris = barisOut($this->g, $this->barang);

    expect(fn () => $this->stok->reserve($baris, 1))->toThrow(StokTidakCukupException::class);
});

it('reservasi dokumen lain mengurangi qty tersedia', function () {
    $this->g->stokAwal($this->barang, $this->g->binA, 5);
    $this->stok->reserve(barisOut($this->g, $this->barang), 4);

    expect(fn () => $this->stok->reserve(barisOut($this->g, $this->barang), 2))
        ->toThrow(StokTidakCukupException::class);
    expect($this->stok->available($this->barang->id, $this->g->binA->id))->toBe(1);
});

it('reservasi PUT dan TRF dari lokasi yang sama ikut dihitung', function () {
    $this->g->stokAwal($this->barang, $this->g->binA, 6);
    $trf = $this->g->dokumen(DocumentType::TRF, [['item' => $this->barang, 'from' => $this->g->binA, 'to' => $this->g->binB]]);
    $this->stok->reserve($trf->lines->first(), 4);

    expect($this->stok->available($this->barang->id, $this->g->binA->id))->toBe(2);
});

it('reservasi dokumen yang sudah selesai atau batal tidak lagi dihitung', function () {
    $this->g->stokAwal($this->barang, $this->g->binA, 6);
    $baris = barisOut($this->g, $this->barang);
    $this->stok->reserve($baris, 4);

    $baris->document->update(['status' => DocumentStatus::Batal]);

    expect($this->stok->available($this->barang->id, $this->g->binA->id))->toBe(6);
});

it('reserve menolak melebihi qty rencana bila rencana diisi', function () {
    $this->g->stokAwal($this->barang, $this->g->binA, 50);
    $baris = barisOut($this->g, $this->barang, planned: 10);
    $this->stok->reserve($baris, 8);

    expect(fn () => $this->stok->reserve($baris, 3))->toThrow(MelebihiRencanaException::class);
    expect($baris->fresh()->qty_scanned)->toBe(8);
});

it('reserve menolak dokumen yang tidak aktif', function () {
    $this->g->stokAwal($this->barang, $this->g->binA, 5);
    $baris = barisOut($this->g, $this->barang);
    $baris->document->update(['status' => DocumentStatus::Selesai]);

    expect(fn () => $this->stok->reserve($baris, 1))->toThrow(DokumenTidakAktifException::class);
});

it('reserve hanya untuk PUT, OUT, dan TRF', function () {
    $grn = $this->g->dokumen(DocumentType::GRN, [['item' => $this->barang, 'from' => $this->g->binA]]);

    expect(fn () => $this->stok->reserve($grn->lines->first(), 1))->toThrow(InvalidArgumentException::class);
});

it('reserve menolak qty nol atau negatif', function (int $qty) {
    $baris = barisOut($this->g, $this->barang);

    expect(fn () => $this->stok->reserve($baris, $qty))->toThrow(InvalidArgumentException::class);
})->with([0, -1]);

it('release mengembalikan qty tersedia dan tidak bisa di bawah nol', function () {
    $this->g->stokAwal($this->barang, $this->g->binA, 5);
    $baris = barisOut($this->g, $this->barang);
    $this->stok->reserve($baris, 3);

    $this->stok->release($baris, 2);

    expect($baris->fresh()->qty_scanned)->toBe(1);
    expect($this->stok->available($this->barang->id, $this->g->binA->id))->toBe(4);
    expect(fn () => $this->stok->release($baris, 2))->toThrow(InvalidArgumentException::class);
});

it('konversi barcode dus 40 menjadi 40 satuan dasar', function () {
    $dus = ItemBarcode::factory()->for($this->barang)->dus(40)->create();

    expect($dus->toBaseQty(1))->toBe(40);
    expect($dus->toBaseQty(3))->toBe(120);
});

it('release menolak qty nol atau negatif tanpa mengubah reservasi', function (int $qty) {
    $this->g->stokAwal($this->barang, $this->g->binA, 5);
    $baris = barisOut($this->g, $this->barang);

    expect(fn () => $this->stok->release($baris, $qty))->toThrow(InvalidArgumentException::class);
    expect($baris->fresh()->qty_scanned)->toBe(0);
})->with([0, -1]);
