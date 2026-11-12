<?php

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Exceptions\DokumenTidakAktifException;
use App\Exceptions\QtyTidakCocokException;
use App\Exceptions\StokTidakCukupException;
use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\StockMovement;
use App\Services\StockService;
use Tests\Support\Gudang;

beforeEach(function () {
    $this->g = Gudang::siapkan();
    $this->stok = app(StockService::class);
});

it('adjust tanpa alasan ditolak', function (string $alasan) {
    $barang = $this->g->barang();
    $this->g->stokAwal($barang, $this->g->binA, 10);
    $opn = $this->g->dokumen(DocumentType::OPN, [['item' => $barang, 'from' => $this->g->binA, 'scanned' => 8, 'system' => 10]]);

    expect(fn () => $this->stok->adjust($opn, $this->g->supervisor, $alasan))->toThrow(InvalidArgumentException::class);
    expect(Gudang::saldo($barang, $this->g->binA))->toBe(10);
    expect(Document::where('type', DocumentType::ADJ)->count())->toBe(0);
})->with(['', '   ']);

it('adjust mencatat approver dan alasan pada dokumen ADJ', function () {
    $kurang = $this->g->barang();
    $lebih = $this->g->barang();
    $pas = $this->g->barang();
    $this->g->stokAwal($kurang, $this->g->binA, 10);
    $this->g->stokAwal($pas, $this->g->binA, 4);
    $opn = $this->g->dokumen(DocumentType::OPN, [
        ['item' => $kurang, 'from' => $this->g->binA, 'scanned' => 7, 'system' => 10],
        ['item' => $lebih, 'from' => $this->g->binA, 'scanned' => 2, 'system' => 0],
        ['item' => $pas, 'from' => $this->g->binA, 'scanned' => 4, 'system' => 4],
    ]);

    $adj = $this->stok->adjust($opn, $this->g->supervisor, 'Barang rusak dan salah taruh');

    expect($adj->type)->toBe(DocumentType::ADJ);
    expect($adj->status)->toBe(DocumentStatus::Selesai);
    expect($adj->number)->toStartWith('ADJ-');
    expect($adj->approved_by)->toBe($this->g->supervisor->id);
    expect($adj->approval_reason)->toBe('Barang rusak dan salah taruh');
    expect($adj->related_document_id)->toBe($opn->id);
    expect($adj->lines)->toHaveCount(2);

    expect(Gudang::saldo($kurang, $this->g->binA))->toBe(7);
    expect(Gudang::saldo($lebih, $this->g->binA))->toBe(2);
    expect(Gudang::saldo($pas, $this->g->binA))->toBe(4);

    $opn->refresh();
    expect($opn->status)->toBe(DocumentStatus::Selesai);
    expect($opn->approved_by)->toBe($this->g->supervisor->id);
    expect($opn->approval_reason)->toBe('Barang rusak dan salah taruh');
    Gudang::assertLedgerCocok();
});

it('adjust tanpa selisih menyelesaikan opname tanpa dokumen ADJ', function () {
    $barang = $this->g->barang();
    $this->g->stokAwal($barang, $this->g->binA, 4);
    $opn = $this->g->dokumen(DocumentType::OPN, [['item' => $barang, 'from' => $this->g->binA, 'scanned' => 4, 'system' => 4]]);

    expect($this->stok->adjust($opn, $this->g->supervisor, 'Cocok semua'))->toBeNull();
    expect($opn->fresh()->status)->toBe(DocumentStatus::Selesai);
});

it('adjust hanya untuk dokumen OPN yang masih aktif', function () {
    $opn = $this->g->dokumen(DocumentType::OPN, [], ['status' => DocumentStatus::Selesai]);
    $grn = $this->g->dokumen(DocumentType::GRN);

    expect(fn () => $this->stok->adjust($opn, $this->g->supervisor, 'x'))->toThrow(DokumenTidakAktifException::class);
    expect(fn () => $this->stok->adjust($grn, $this->g->supervisor, 'x'))->toThrow(InvalidArgumentException::class);
});

it('reverse membuat transaksi balik tanpa mengubah movement lama', function () {
    $barang = $this->g->barang();
    $grn = $this->g->dokumen(DocumentType::GRN, [['item' => $barang, 'to' => $this->g->binA, 'scanned' => 9]]);
    $this->stok->receive($grn, $this->g->staf);
    $movementAsli = StockMovement::sole()->only(['id', 'qty', 'from_location_id', 'to_location_id']);

    $balik = $this->stok->reverse($grn->fresh(), $this->g->supervisor, 'Salah input supplier');

    expect($balik->type)->toBe(DocumentType::ADJ);
    expect($balik->reversal_of_id)->toBe($grn->id);
    expect($balik->approval_reason)->toBe('Salah input supplier');
    expect(Gudang::saldo($barang, $this->g->binA))->toBe(0);
    expect(StockMovement::find($movementAsli['id'])->only(array_keys($movementAsli)))->toBe($movementAsli);

    $kebalikan = StockMovement::where('id', '!=', $movementAsli['id'])->sole();
    expect($kebalikan->from_location_id)->toBe($this->g->binA->id);
    expect($kebalikan->to_location_id)->toBeNull();
    expect($kebalikan->qty)->toBe(9);
    Gudang::assertLedgerCocok();
});

it('reverse ditolak bila stok sudah terpakai, dua kali, atau dokumen belum selesai', function () {
    $barang = $this->g->barang();
    $grn = $this->g->dokumen(DocumentType::GRN, [['item' => $barang, 'to' => $this->g->binA, 'scanned' => 5]]);
    $draft = $this->g->dokumen(DocumentType::GRN, [['item' => $barang, 'to' => $this->g->binA, 'scanned' => 1]]);
    $this->stok->receive($grn, $this->g->staf);
    $this->stok->issue($this->g->dokumen(DocumentType::OUT, [
        ['item' => $barang, 'from' => $this->g->binA, 'planned' => 3, 'scanned' => 3, 'packed' => 3],
    ]), $this->g->staf);

    expect(fn () => $this->stok->reverse($grn->fresh(), $this->g->supervisor, 'balik'))->toThrow(StokTidakCukupException::class);
    expect(fn () => $this->stok->reverse($draft, $this->g->supervisor, 'balik'))->toThrow(DokumenTidakAktifException::class);

    $grn2 = $this->g->dokumen(DocumentType::GRN, [['item' => $barang, 'to' => $this->g->binB, 'scanned' => 1]]);
    $this->stok->receive($grn2, $this->g->staf);
    $this->stok->reverse($grn2->fresh(), $this->g->supervisor, 'balik');
    expect(fn () => $this->stok->reverse($grn2->fresh(), $this->g->supervisor, 'balik lagi'))->toThrow(DomainException::class);
    Gudang::assertLedgerCocok();
});

it('reverse ditolak untuk dokumen selesai tanpa transaksi stok', function () {
    $barang = $this->g->barang();
    $this->g->stokAwal($barang, $this->g->binA, 4);
    $opn = $this->g->dokumen(DocumentType::OPN, [['item' => $barang, 'from' => $this->g->binA, 'scanned' => 4, 'system' => 4]]);
    $this->stok->adjust($opn, $this->g->supervisor, 'Cocok');

    expect(fn () => $this->stok->reverse($opn->fresh(), $this->g->supervisor, 'balik'))
        ->toThrow(QtyTidakCocokException::class);
    expect(Document::where('type', DocumentType::ADJ)->count())->toBe(0);
});

it('log persetujuan opname mencatat status sebelum dan alasan', function () {
    $barang = $this->g->barang();
    $this->g->stokAwal($barang, $this->g->binA, 4);
    $opn = $this->g->dokumen(DocumentType::OPN, [['item' => $barang, 'from' => $this->g->binA, 'scanned' => 3, 'system' => 4]], [
        'status' => DocumentStatus::Proses,
    ]);

    $adj = $this->stok->adjust($opn, $this->g->supervisor, 'Hilang 1');

    $log = ActivityLog::where('action', 'opname.disetujui')->sole();
    expect($log->user_id)->toBe($this->g->supervisor->id);
    expect($log->before)->toBe(['status' => 'proses']);
    expect($log->after['alasan'])->toBe('Hilang 1');
    expect($log->after['adj'])->toBe($adj->number);
});
