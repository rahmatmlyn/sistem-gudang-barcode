<?php

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\LocationType;
use App\Enums\OpnameLocationStatus;
use App\Enums\UserRole;
use App\Exceptions\DokumenTidakAktifException;
use App\Models\User;
use App\Services\OpnameService;
use App\Services\StockService;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\Gudang;

beforeEach(function () {
    $this->g = Gudang::siapkan();
    $this->opname = app(OpnameService::class);
});

it('memilih zona mengisi lokasi zona beserta turunannya', function () {
    $zona = $this->g->lokasi('C', LocationType::Zona);
    $rak = $this->g->lokasi('C-01', LocationType::Rak, $zona);
    $bin = $this->g->lokasi('C-01-01', LocationType::Bin, $rak);
    $opn = $this->g->dokumen(DocumentType::OPN);

    $this->opname->setLocations($opn, [$zona->id, $this->g->binA->id]);

    expect($opn->opnameLocations()->pluck('location_id')->sort()->values()->all())
        ->toBe(collect([$zona->id, $rak->id, $bin->id, $this->g->binA->id])->sort()->values()->all());
});

it('selesai hitung lokasi mengisi qty_system dan baris barang yang tidak di-scan', function () {
    [$dihitung, $terlewat] = [$this->g->barang(), $this->g->barang()];
    $this->g->stokAwal($dihitung, $this->g->binA, 10);
    $this->g->stokAwal($terlewat, $this->g->binA, 4);
    $opn = $this->g->dokumen(DocumentType::OPN, [['item' => $dihitung, 'from' => $this->g->binA, 'scanned' => 9]]);
    $this->opname->setLocations($opn, [$this->g->binA->id]);

    $this->opname->finishLocation($opn, $this->g->binA, $this->g->staf, kosong: false);

    expect($opn->lines()->orderBy('item_id')->get(['item_id', 'qty_scanned', 'qty_system'])->toArray())->toBe([
        ['item_id' => $dihitung->id, 'qty_scanned' => 9, 'qty_system' => 10],
        ['item_id' => $terlewat->id, 'qty_scanned' => 0, 'qty_system' => 4],
    ]);
    expect($opn->opnameLocations()->sole())->status->toBe(OpnameLocationStatus::Selesai)->counted_by->toBe($this->g->staf->id);
    expect($opn->fresh()->status)->toBe(DocumentStatus::Proses);
});

it('lokasi yang sudah selesai dihitung tidak bisa diselesaikan lagi', function () {
    $opn = $this->g->dokumen(DocumentType::OPN);
    $this->opname->setLocations($opn, [$this->g->binA->id]);
    $this->opname->finishLocation($opn, $this->g->binA, $this->g->staf, kosong: true);

    expect($opn->opnameLocations()->sole()->status)->toBe(OpnameLocationStatus::Kosong);
    expect(fn () => $this->opname->finishLocation($opn, $this->g->binA, $this->g->staf, kosong: true))
        ->toThrow(DokumenTidakAktifException::class);
});

it('lokasi kosong ditolak bila ada barang yang sudah di-scan', function () {
    $opn = $this->g->dokumen(DocumentType::OPN, [['item' => $this->g->barang(), 'from' => $this->g->binA, 'scanned' => 2]]);
    $this->opname->setLocations($opn, [$this->g->binA->id]);

    expect(fn () => $this->opname->finishLocation($opn, $this->g->binA, $this->g->staf, kosong: true))
        ->toThrow(InvalidArgumentException::class);
});

it('qty_system memakai qty tersedia saat ada reservasi picking', function () {
    $barang = $this->g->barang();
    $this->g->stokAwal($barang, $this->g->binA, 10);
    $out = $this->g->dokumen(DocumentType::OUT, [['item' => $barang, 'from' => $this->g->binA, 'planned' => 3]]);
    app(StockService::class)->reserve($out->lines->first(), 3);

    $opn = $this->g->opnameDiajukan([[$barang, 7]]);
    $adj = $this->opname->approve($opn, $this->g->supervisor, 'Cocok');

    expect($opn->lines()->sole()->qty_system)->toBe(7)
        ->and($adj)->toBeNull()
        ->and(Gudang::saldo($barang, $this->g->binA))->toBe(10);
});

it('approval membuat ADJ dengan alasan dan nama supervisor (UAT-16)', function () {
    $barang = $this->g->barang();
    $this->g->stokAwal($barang, $this->g->binA, 10);
    $opn = $this->g->opnameDiajukan([[$barang, 8]]);

    $adj = $this->opname->approve($opn, $this->g->supervisor, 'Barang rusak dibuang');

    expect($adj)->type->toBe(DocumentType::ADJ)
        ->approval_reason->toBe('Barang rusak dibuang')
        ->approved_by->toBe($this->g->supervisor->id)
        ->related_document_id->toBe($opn->id);
    expect($opn->fresh()->status)->toBe(DocumentStatus::Selesai)
        ->and(Gudang::saldo($barang, $this->g->binA))->toBe(8);
    Gudang::assertLedgerCocok();
});

it('approval tanpa alasan ditolak (UAT-17)', function () {
    $barang = $this->g->barang();
    $this->g->stokAwal($barang, $this->g->binA, 10);
    $opn = $this->g->opnameDiajukan([[$barang, 8]]);

    expect(fn () => $this->opname->approve($opn, $this->g->supervisor, '   '))->toThrow(InvalidArgumentException::class);
    expect($opn->fresh()->status)->toBe(DocumentStatus::Proses)
        ->and(Gudang::saldo($barang, $this->g->binA))->toBe(10);
});

it('admin dan staf tidak bisa approve', function (UserRole $peran) {
    $opn = $this->g->opnameDiajukan([]);

    expect(fn () => $this->opname->approve($opn, User::factory()->create(['role' => $peran]), 'Alasan'))
        ->toThrow(AuthorizationException::class);
})->with([UserRole::Admin, UserRole::Staf]);

it('tidak bisa diajukan bila masih ada lokasi belum dihitung', function () {
    $opn = $this->g->dokumen(DocumentType::OPN);
    $this->opname->setLocations($opn, [$this->g->binA->id, $this->g->binB->id]);
    $this->opname->finishLocation($opn, $this->g->binA, $this->g->staf, kosong: true);

    expect(fn () => $this->opname->submit($opn, $this->g->staf))->toThrow(InvalidArgumentException::class, 'B-01-01');
});

it('approval hanya untuk opname yang sudah diajukan', function () {
    $opn = $this->g->dokumen(DocumentType::OPN);
    $this->opname->setLocations($opn, [$this->g->binA->id]);

    expect(fn () => $this->opname->approve($opn, $this->g->supervisor, 'Alasan'))->toThrow(DokumenTidakAktifException::class);
});

it('menolak opname membatalkan dokumen dengan alasan tanpa mengubah stok', function () {
    $barang = $this->g->barang();
    $this->g->stokAwal($barang, $this->g->binA, 10);
    $opn = $this->g->opnameDiajukan([[$barang, 2]]);

    $this->opname->reject($opn, $this->g->supervisor, 'Hitung ulang, selisih terlalu besar');

    expect($opn->fresh())->status->toBe(DocumentStatus::Batal)
        ->approval_reason->toBe('Hitung ulang, selisih terlalu besar');
    expect(Gudang::saldo($barang, $this->g->binA))->toBe(10);
});
