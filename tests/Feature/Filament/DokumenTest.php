<?php

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Filament\Resources\Adjustments\AdjustmentResource;
use App\Filament\Resources\GoodsReceipts\GoodsReceiptResource;
use App\Filament\Resources\GoodsReceipts\Pages\CreateGoodsReceipt;
use App\Filament\Resources\GoodsReceipts\Pages\ListGoodsReceipts;
use App\Filament\Resources\GoodsReceipts\Pages\ViewGoodsReceipt;
use App\Filament\Resources\OutboundOrders\OutboundOrderResource;
use App\Filament\Resources\OutboundOrders\Pages\ViewOutboundOrder;
use App\Filament\Resources\Putaways\PutawayResource;
use App\Filament\Resources\StockMovements\StockMovementResource;
use App\Filament\Resources\StockOpnames\StockOpnameResource;
use App\Filament\Resources\Transfers\Pages\CreateTransfer;
use App\Filament\Resources\Transfers\TransferResource;
use App\Models\ActivityLog;
use App\Models\Document;
use App\Services\StockService;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Livewire\Livewire;
use Tests\Support\Gudang;

beforeEach(function () {
    $this->g = Gudang::siapkan();
    $this->batalFakeRepeater = Repeater::fake();
});
afterEach(fn () => ($this->batalFakeRepeater)());

function grnSelesai(Gudang $g, int $qty = 5): Document
{
    $g->stokAwal($g->barang(), $g->binA, $qty);

    return Document::ofType(DocumentType::GRN)->where('status', 'selesai')->latest('id')->firstOrFail();
}

it('admin membuat GRN bernomor otomatis dengan baris barang', function () {
    $admin = sebagai(UserRole::Admin);
    $barang = $this->g->barang();

    Livewire::test(CreateGoodsReceipt::class)
        ->fillForm(['warehouse_id' => $this->g->gudang->id, 'partner' => 'PT Sumber', 'reference' => 'PO-77',
            'lines' => [['item_id' => $barang->id, 'qty_planned' => 12]]])
        ->call('create')
        ->assertHasNoFormErrors();

    $grn = Document::ofType(DocumentType::GRN)->sole();
    expect($grn)->number->toBe(sprintf('GRN-%d-00001', now()->year))
        ->status->toBe(DocumentStatus::Draft)
        ->created_by->toBe($admin->id);
    expect($grn->lines()->sole())->item_id->toBe($barang->id)->qty_planned->toBe(12);
});

it('dokumen selesai tidak bisa diedit atau dihapus (UAT-25)', function () {
    sebagai(UserRole::Admin);
    $selesai = grnSelesai($this->g);
    $draft = $this->g->dokumen(DocumentType::GRN, [['item' => $this->g->barang(), 'planned' => 3]]);

    $this->get(GoodsReceiptResource::getUrl('edit', ['record' => $selesai]))->assertForbidden();
    Livewire::test(ListGoodsReceipts::class)
        ->assertTableActionHidden(EditAction::class, $selesai)
        ->assertTableActionVisible(EditAction::class, $draft);
    Livewire::test(ViewGoodsReceipt::class, ['record' => $selesai->getRouteKey()])
        ->assertActionHidden(EditAction::class)
        ->assertActionHidden(DeleteAction::class)
        ->assertActionVisible('balik');
});

it('transaksi balik dari panel membuat ADJ dan mengembalikan stok', function () {
    sebagai(UserRole::Supervisor);
    $grn = grnSelesai($this->g);

    Livewire::test(ViewGoodsReceipt::class, ['record' => $grn->getRouteKey()])
        ->callAction('balik', data: ['alasan' => 'Salah input supplier'])
        ->assertHasNoActionErrors();

    expect(Document::where('reversal_of_id', $grn->id)->sole()->approval_reason)->toBe('Salah input supplier');
    expect(Gudang::saldo($grn->lines->first()->item, $this->g->binA))->toBe(0);
    Livewire::test(ViewGoodsReceipt::class, ['record' => $grn->getRouteKey()])->assertActionHidden('balik');
});

it('transaksi balik tanpa alasan ditolak', function () {
    sebagai(UserRole::Admin);
    $grn = grnSelesai($this->g);

    Livewire::test(ViewGoodsReceipt::class, ['record' => $grn->getRouteKey()])
        ->callAction('balik', data: ['alasan' => ''])
        ->assertHasActionErrors(['alasan' => 'required']);

    expect(Document::where('reversal_of_id', $grn->id)->exists())->toBeFalse();
});

it('transaksi balik yang membuat stok minus ditolak dengan pesan', function () {
    sebagai(UserRole::Admin);
    $grn = grnSelesai($this->g);
    $trf = $this->g->dokumen(DocumentType::TRF, [['item' => $grn->lines->first()->item, 'from' => $this->g->binA, 'to' => $this->g->binB, 'scanned' => 5]]);
    app(StockService::class)->transfer($trf, $this->g->staf);

    Livewire::test(ViewGoodsReceipt::class, ['record' => $grn->getRouteKey()])
        ->callAction('balik', data: ['alasan' => 'Koreksi'])
        ->assertNotified('Transaksi balik gagal');

    expect(Document::where('reversal_of_id', $grn->id)->exists())->toBeFalse();
});

it('staf tidak bisa mengakses resource dokumen (UAT-23)', function (string $resource) {
    sebagai(UserRole::Staf);

    $this->get($resource::getUrl('index'))->assertForbidden();
})->with([GoodsReceiptResource::class, OutboundOrderResource::class, TransferResource::class, StockOpnameResource::class,
    PutawayResource::class, AdjustmentResource::class, StockMovementResource::class]);

it('admin dan supervisor membuka daftar dan form dokumen', function (UserRole $peran) {
    sebagai($peran);

    foreach ([GoodsReceiptResource::class, OutboundOrderResource::class, TransferResource::class, StockOpnameResource::class] as $resource) {
        $this->get($resource::getUrl('index'))->assertOk();
        $this->get($resource::getUrl('create'))->assertOk();
    }
    foreach ([PutawayResource::class, AdjustmentResource::class, StockMovementResource::class] as $resource) {
        $this->get($resource::getUrl('index'))->assertOk();
    }
})->with([UserRole::Admin, UserRole::Supervisor]);

it('rilis pesanan keluar ke picking dari panel', function () {
    sebagai(UserRole::Supervisor);
    $barang = $this->g->barang();
    $this->g->stokAwal($barang, $this->g->binA, 10);
    $out = $this->g->dokumen(DocumentType::OUT, [['item' => $barang, 'planned' => 4]]);

    Livewire::test(ViewOutboundOrder::class, ['record' => $out->getRouteKey()])
        ->callAction('rilis')
        ->assertNotified('Pesanan dirilis ke picking');

    expect($out->fresh()->status)->toBe(DocumentStatus::Proses)
        ->and($out->lines()->sole()->from_location_id)->toBe($this->g->binA->id);
});

it('rilis dengan stok kurang menampilkan pesan dan pesanan tetap draft', function () {
    sebagai(UserRole::Supervisor);
    $out = $this->g->dokumen(DocumentType::OUT, [['item' => $this->g->barang(), 'planned' => 4]]);

    Livewire::test(ViewOutboundOrder::class, ['record' => $out->getRouteKey()])
        ->callAction('rilis')
        ->assertNotified('Rilis gagal');

    expect($out->fresh()->status)->toBe(DocumentStatus::Draft);
});

it('membatalkan dokumen dengan alasan tercatat di log', function () {
    $admin = sebagai(UserRole::Admin);
    $out = $this->g->dokumen(DocumentType::OUT, [['item' => $this->g->barang(), 'planned' => 4]]);

    Livewire::test(ViewOutboundOrder::class, ['record' => $out->getRouteKey()])
        ->callAction('batalkan', data: ['alasan' => 'Dibatalkan pelanggan']);

    expect($out->fresh()->status)->toBe(DocumentStatus::Batal);
    expect(ActivityLog::where('action', 'dokumen.dibatalkan')->sole())
        ->user_id->toBe($admin->id)
        ->after->toMatchArray(['alasan' => 'Dibatalkan pelanggan']);
});

it('transfer menolak lokasi asal yang sama dengan tujuan', function () {
    sebagai(UserRole::Admin);

    Livewire::test(CreateTransfer::class)
        ->fillForm(['warehouse_id' => $this->g->gudang->id, 'lines' => [[
            'item_id' => $this->g->barang()->id, 'from_location_id' => $this->g->binA->id,
            'to_location_id' => $this->g->binA->id, 'qty_planned' => 2,
        ]]])
        ->call('create')
        ->assertHasFormErrors(['lines.0.to_location_id']);
});
