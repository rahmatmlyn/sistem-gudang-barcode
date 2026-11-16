<?php

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Exceptions\AlokasiGagalException;
use App\Exceptions\DokumenTidakAktifException;
use App\Models\DocumentLine;
use App\Services\PickingAllocator;
use Tests\Support\Gudang;

beforeEach(function () {
    $this->g = Gudang::siapkan();
    $this->alokasi = app(PickingAllocator::class);
});

/** @return list<array{0: string, 1: string, 2: int}> [kode lokasi, sku, qty rencana] urut daftar picking */
function daftarPicking($out): array
{
    return $out->fresh()->pickingLines()->with(['fromLocation', 'item'])->get()
        ->map(fn (DocumentLine $baris) => [$baris->fromLocation->code, $baris->item->sku, $baris->qty_planned])
        ->all();
}

it('daftar picking diurutkan per lokasi (UAT-07)', function () {
    $kode = ['C-02-01', 'A-02-01', 'B-03-02', 'A-01-02', 'B-01-02'];
    $baris = [];
    foreach ($kode as $urutan => $code) {
        $barang = $this->g->barang(['sku' => 'SKU-0000'.($urutan + 1)]);
        $this->g->stokAwal($barang, $this->g->lokasi($code), 10);
        $baris[] = ['item' => $barang, 'planned' => 2];
    }
    $out = $this->g->dokumen(DocumentType::OUT, $baris);

    $this->alokasi->allocate($out, $this->g->supervisor);

    expect(daftarPicking($out))->toBe([
        ['A-01-02', 'SKU-00004', 2], ['A-02-01', 'SKU-00002', 2], ['B-01-02', 'SKU-00005', 2],
        ['B-03-02', 'SKU-00003', 2], ['C-02-01', 'SKU-00001', 2],
    ]);
    expect($out->fresh()->status)->toBe(DocumentStatus::Proses);
});

it('memecah baris ke beberapa bin bila satu bin tidak cukup', function () {
    $barang = $this->g->barang(['sku' => 'SKU-00001']);
    $this->g->stokAwal($barang, $this->g->binA, 20);
    $this->g->stokAwal($barang, $this->g->binB, 15);
    $out = $this->g->dokumen(DocumentType::OUT, [['item' => $barang, 'planned' => 30]]);

    $this->alokasi->allocate($out, $this->g->supervisor);

    expect(daftarPicking($out))->toBe([['A-01-01', 'SKU-00001', 20], ['B-01-01', 'SKU-00001', 10]]);
});

it('gagal utuh bila stok total kurang', function () {
    $cukup = $this->g->barang();
    $kurang = $this->g->barang(['sku' => 'SKU-KURANG']);
    $this->g->stokAwal($cukup, $this->g->binA, 50);
    $this->g->stokAwal($kurang, $this->g->binA, 10);
    $out = $this->g->dokumen(DocumentType::OUT, [['item' => $cukup, 'planned' => 5], ['item' => $kurang, 'planned' => 30]]);

    expect(fn () => $this->alokasi->allocate($out, $this->g->supervisor))
        ->toThrow(AlokasiGagalException::class, 'SKU-KURANG');

    expect($out->fresh()->status)->toBe(DocumentStatus::Draft)
        ->and($out->lines()->whereNotNull('from_location_id')->count())->toBe(0)
        ->and($out->lines()->count())->toBe(2);
});

it('tidak memakai stok yang sudah dialokasikan ke pesanan lain', function () {
    $barang = $this->g->barang(['sku' => 'SKU-00001']);
    $this->g->stokAwal($barang, $this->g->binA, 20);
    $this->g->stokAwal($barang, $this->g->binB, 10);
    $pertama = $this->g->dokumen(DocumentType::OUT, [['item' => $barang, 'planned' => 15]]);
    $kedua = $this->g->dokumen(DocumentType::OUT, [['item' => $barang, 'planned' => 10]]);

    $this->alokasi->allocate($pertama, $this->g->supervisor);
    $this->alokasi->allocate($kedua, $this->g->supervisor);

    expect(daftarPicking($kedua))->toBe([['A-01-01', 'SKU-00001', 5], ['B-01-01', 'SKU-00001', 5]]);
});

it('tidak mengalokasikan dari area terima', function () {
    $barang = $this->g->barang();
    $this->g->stokAwal($barang, $this->g->rcv, 50);
    $out = $this->g->dokumen(DocumentType::OUT, [['item' => $barang, 'planned' => 5]]);

    expect(fn () => $this->alokasi->allocate($out, $this->g->supervisor))->toThrow(AlokasiGagalException::class);
});

it('hanya pesanan keluar draft yang bisa dirilis', function () {
    $barang = $this->g->barang();
    $this->g->stokAwal($barang, $this->g->binA, 50);
    $out = $this->g->dokumen(DocumentType::OUT, [['item' => $barang, 'planned' => 5]], ['status' => DocumentStatus::Proses]);

    expect(fn () => $this->alokasi->allocate($out, $this->g->supervisor))
        ->toThrow(DokumenTidakAktifException::class);
});
