<?php

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Events\StokBerubah;
use App\Exceptions\DokumenTidakAktifException;
use App\Exceptions\QtyTidakCocokException;
use App\Exceptions\StokTidakCukupException;
use App\Models\ActivityLog;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Support\Facades\Event;
use Tests\Support\Gudang;

beforeEach(function () {
    $this->g = Gudang::siapkan();
    $this->stok = app(StockService::class);
});

it('draft GRN tidak mengubah saldo', function () {
    $this->g->dokumen(DocumentType::GRN, [
        ['item' => $this->g->barang(), 'to' => $this->g->rcv, 'scanned' => 5],
        ['item' => $this->g->barang(), 'to' => $this->g->rcv, 'scanned' => 7],
        ['item' => $this->g->barang(), 'to' => $this->g->rcv, 'scanned' => 1],
    ]);

    expect(StockBalance::count())->toBe(0);
    expect(StockMovement::count())->toBe(0);
});

it('receive menambah saldo area terima dan menulis movement', function () {
    $barang = $this->g->barang();
    $grn = $this->g->dokumen(DocumentType::GRN, [['item' => $barang, 'scanned' => 12]]);

    $this->stok->receive($grn, $this->g->staf);

    expect(Gudang::saldo($barang, $this->g->rcv))->toBe(12);
    $movement = StockMovement::sole();
    expect($movement->from_location_id)->toBeNull();
    expect($movement->to_location_id)->toBe($this->g->rcv->id);
    expect($movement->type)->toBe(DocumentType::GRN);
    expect($movement->user_id)->toBe($this->g->staf->id);
    expect($movement->document_line_id)->toBe($grn->lines->first()->id);

    $grn->refresh();
    expect($grn->status)->toBe(DocumentStatus::Selesai);
    expect($grn->completed_by)->toBe($this->g->staf->id);
    expect($grn->completed_at)->not->toBeNull();
    Gudang::assertLedgerCocok();
});

it('baris dengan qty scan 0 tidak menghasilkan movement', function () {
    $grn = $this->g->dokumen(DocumentType::GRN, [
        ['item' => $this->g->barang(), 'scanned' => 3],
        ['item' => $this->g->barang(), 'scanned' => 0],
    ]);

    $this->stok->receive($grn, $this->g->staf);

    expect(StockMovement::count())->toBe(1);
});

it('putaway memindahkan dari area terima ke rak, total tetap', function () {
    $barang = $this->g->barang();
    $this->g->stokAwal($barang, $this->g->rcv, 10);
    $put = $this->g->dokumen(DocumentType::PUT, [['item' => $barang, 'from' => $this->g->rcv, 'to' => $this->g->binA, 'scanned' => 6]]);

    $this->stok->putaway($put, $this->g->staf);

    expect(Gudang::saldo($barang, $this->g->rcv))->toBe(4);
    expect(Gudang::saldo($barang, $this->g->binA))->toBe(6);
    expect((int) StockBalance::where('item_id', $barang->id)->sum('qty'))->toBe(10);
    Gudang::assertLedgerCocok();
});

it('transfer: asal berkurang, tujuan bertambah, total tetap', function () {
    $barang = $this->g->barang();
    $this->g->stokAwal($barang, $this->g->binA, 8);
    $trf = $this->g->dokumen(DocumentType::TRF, [['item' => $barang, 'from' => $this->g->binA, 'to' => $this->g->binB, 'scanned' => 3]]);

    $this->stok->transfer($trf, $this->g->staf);

    expect(Gudang::saldo($barang, $this->g->binA))->toBe(5);
    expect(Gudang::saldo($barang, $this->g->binB))->toBe(3);
    Gudang::assertLedgerCocok();
});

it('issue mengurangi saldo hanya saat dokumen selesai', function () {
    $barang = $this->g->barang();
    $this->g->stokAwal($barang, $this->g->binA, 10);
    $out = $this->g->dokumen(DocumentType::OUT, [
        ['item' => $barang, 'from' => $this->g->binA, 'planned' => 4, 'scanned' => 4, 'packed' => 4],
    ]);

    expect(Gudang::saldo($barang, $this->g->binA))->toBe(10);

    $this->stok->issue($out, $this->g->staf);

    expect(Gudang::saldo($barang, $this->g->binA))->toBe(6);
    expect(StockMovement::where('type', DocumentType::OUT)->sole()->to_location_id)->toBeNull();
    Gudang::assertLedgerCocok();
});

it('issue ditolak bila qty packing atau picking tidak sama dengan pesanan', function (int $scanned, int $packed) {
    $barang = $this->g->barang();
    $this->g->stokAwal($barang, $this->g->binA, 10);
    $out = $this->g->dokumen(DocumentType::OUT, [
        ['item' => $barang, 'from' => $this->g->binA, 'planned' => 5, 'scanned' => $scanned, 'packed' => $packed],
    ]);

    expect(fn () => $this->stok->issue($out, $this->g->staf))->toThrow(QtyTidakCocokException::class);
    expect(Gudang::saldo($barang, $this->g->binA))->toBe(10);
    expect($out->fresh()->status)->toBe(DocumentStatus::Draft);
})->with([
    'packing kurang 1' => [5, 4],
    'picking kurang 1' => [4, 4],
]);

it('penyelesaian yang membuat saldo minus ditolak utuh', function () {
    $ada = $this->g->barang();
    $kurang = $this->g->barang();
    $this->g->stokAwal($ada, $this->g->binA, 5);
    $this->g->stokAwal($kurang, $this->g->binA, 1);
    $trf = $this->g->dokumen(DocumentType::TRF, [
        ['item' => $ada, 'from' => $this->g->binA, 'to' => $this->g->binB, 'scanned' => 5],
        ['item' => $kurang, 'from' => $this->g->binA, 'to' => $this->g->binB, 'scanned' => 2],
    ]);
    $jumlahMovement = StockMovement::count();

    expect(fn () => $this->stok->transfer($trf, $this->g->staf))->toThrow(StokTidakCukupException::class);

    expect(Gudang::saldo($ada, $this->g->binA))->toBe(5);
    expect(Gudang::saldo($kurang, $this->g->binA))->toBe(1);
    expect(StockMovement::count())->toBe($jumlahMovement);
    expect($trf->fresh()->status)->toBe(DocumentStatus::Draft);
});

it('dokumen yang sudah selesai atau batal tidak bisa diselesaikan lagi', function (DocumentStatus $status) {
    $grn = $this->g->dokumen(DocumentType::GRN, [['item' => $this->g->barang(), 'scanned' => 3]], ['status' => $status]);

    expect(fn () => $this->stok->receive($grn, $this->g->staf))->toThrow(DokumenTidakAktifException::class);
    expect(StockMovement::count())->toBe(0);
})->with([DocumentStatus::Selesai, DocumentStatus::Batal]);

it('menolak menyelesaikan dokumen dengan method tipe yang salah', function () {
    $out = $this->g->dokumen(DocumentType::OUT, [['item' => $this->g->barang(), 'from' => $this->g->binA, 'scanned' => 1]]);

    expect(fn () => $this->stok->receive($out, $this->g->staf))->toThrow(InvalidArgumentException::class);
});

it('menolak dokumen tanpa qty yang bisa diproses', function () {
    $grn = $this->g->dokumen(DocumentType::GRN, [['item' => $this->g->barang(), 'scanned' => 0]]);

    expect(fn () => $this->stok->receive($grn, $this->g->staf))->toThrow(QtyTidakCocokException::class);
});

it('mencatat activity log dengan user, aksi, dan dokumen', function () {
    $grn = $this->g->dokumen(DocumentType::GRN, [['item' => $this->g->barang(), 'scanned' => 2]]);

    $this->stok->receive($grn, $this->g->staf);

    $log = ActivityLog::where('subject_type', $grn->getMorphClass())->sole();
    expect($log->user_id)->toBe($this->g->staf->id);
    expect($log->action)->toBe('dokumen.selesai');
    expect($log->subject->is($grn))->toBeTrue();
    expect($log->before['status'])->toBe('draft');
    expect($log->after['status'])->toBe('selesai');
});

it('mengirim event StokBerubah untuk barang yang berubah', function () {
    Event::fake([StokBerubah::class]);
    $barang = $this->g->barang();
    $grn = $this->g->dokumen(DocumentType::GRN, [['item' => $barang, 'scanned' => 2]]);

    $this->stok->receive($grn, $this->g->staf);

    Event::assertDispatched(StokBerubah::class, fn (StokBerubah $event) => $event->itemIds === [$barang->id]);
});

it('putaway sebagian: baris yang belum di-scan tidak menghalangi penyelesaian', function () {
    [$diputaway, $belum] = [$this->g->barang(), $this->g->barang()];
    $this->g->stokAwal($diputaway, $this->g->rcv, 5);
    $this->g->stokAwal($belum, $this->g->rcv, 3);
    $put = $this->g->dokumen(DocumentType::PUT, [
        ['item' => $diputaway, 'from' => $this->g->rcv, 'to' => $this->g->binA, 'planned' => 5, 'scanned' => 5],
        ['item' => $belum, 'from' => $this->g->rcv, 'planned' => 3],
    ]);

    $this->stok->putaway($put, $this->g->staf);

    expect(Gudang::saldo($diputaway, $this->g->binA))->toBe(5)
        ->and(Gudang::saldo($belum, $this->g->rcv))->toBe(3);
});
