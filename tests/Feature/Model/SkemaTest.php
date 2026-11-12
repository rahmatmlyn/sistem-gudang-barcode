<?php

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\LocationType;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\Item;
use App\Models\ItemBarcode;
use App\Models\Location;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function movementContoh(): StockMovement
{
    return StockMovement::create([
        'item_id' => Item::factory()->create()->id,
        'to_location_id' => Location::factory()->create()->id,
        'qty' => 5,
        'type' => DocumentType::GRN,
    ]);
}

it('menolak update dan delete stock_movements di level database', function () {
    $movement = movementContoh();

    expect(dalamSavepoint(fn () => DB::table('stock_movements')->where('id', $movement->id)->update(['qty' => 99])))
        ->toThrow(QueryException::class, 'stock_movements tidak boleh diubah');

    expect(dalamSavepoint(fn () => DB::table('stock_movements')->where('id', $movement->id)->delete()))
        ->toThrow(QueryException::class, 'stock_movements tidak boleh diubah');

    expect(DB::table('stock_movements')->where('id', $movement->id)->value('qty'))->toBe(5);
});

it('menolak update dan delete stock_movements lewat model', function () {
    $movement = movementContoh();

    expect(fn () => $movement->update(['qty' => 99]))->toThrow(LogicException::class);
    expect(fn () => $movement->delete())->toThrow(LogicException::class);
    expect($movement->fresh()->qty)->toBe(5);
});

it('menolak movement tanpa lokasi asal maupun tujuan', function () {
    $item = Item::factory()->create();

    expect(dalamSavepoint(fn () => StockMovement::create(['item_id' => $item->id, 'qty' => 1, 'type' => DocumentType::ADJ])))
        ->toThrow(QueryException::class, 'stock_movements_has_location');
});

it('menolak movement dengan qty nol atau negatif', function (int $qty) {
    $item = Item::factory()->create();
    $lokasi = Location::factory()->create();

    expect(dalamSavepoint(fn () => StockMovement::create([
        'item_id' => $item->id, 'to_location_id' => $lokasi->id, 'qty' => $qty, 'type' => DocumentType::GRN,
    ])))->toThrow(QueryException::class, 'stock_movements_qty_positive');
})->with([0, -3]);

it('menolak saldo negatif lewat check constraint', function () {
    $item = Item::factory()->create();
    $lokasi = Location::factory()->create();

    expect(dalamSavepoint(fn () => StockBalance::create(['item_id' => $item->id, 'location_id' => $lokasi->id, 'qty' => -1])))
        ->toThrow(QueryException::class, 'stock_balances_qty_non_negative');
});

it('saldo unik per pasangan item dan lokasi', function () {
    $item = Item::factory()->create();
    $lokasi = Location::factory()->create();
    StockBalance::create(['item_id' => $item->id, 'location_id' => $lokasi->id, 'qty' => 1]);

    expect(dalamSavepoint(fn () => StockBalance::create(['item_id' => $item->id, 'location_id' => $lokasi->id, 'qty' => 2])))
        ->toThrow(QueryException::class, 'stock_balances_item_id_location_id_unique');
});

it('relasi item punya banyak barcode dan barcode unik', function () {
    $item = Item::factory()->create();
    ItemBarcode::factory()->for($item)->create(['barcode' => '8990000000017']);
    ItemBarcode::factory()->for($item)->dus(24)->create(['barcode' => 'DUS-001']);

    expect($item->barcodes)->toHaveCount(2);
    expect(dalamSavepoint(fn () => ItemBarcode::factory()->create(['barcode' => 'DUS-001'])))
        ->toThrow(QueryException::class, 'item_barcodes_barcode_unique');
});

it('menolak faktor konversi kurang dari 1', function () {
    expect(dalamSavepoint(fn () => ItemBarcode::factory()->create(['factor' => 0])))
        ->toThrow(QueryException::class, 'item_barcodes_factor_positive');
});

it('lokasi bertingkat via parent_id', function () {
    $gudang = Warehouse::factory()->create();
    $zona = Location::factory()->for($gudang)->ofType(LocationType::Zona)->create(['code' => 'A']);
    $rak = Location::factory()->for($gudang)->ofType(LocationType::Rak)->create(['code' => 'A-01', 'parent_id' => $zona->id]);
    $bin = Location::factory()->for($gudang)->create(['code' => 'A-01-02', 'parent_id' => $rak->id]);

    expect($bin->parent->is($rak))->toBeTrue();
    expect($rak->parent->is($zona))->toBeTrue();
    expect($zona->children->pluck('code')->all())->toBe(['A-01']);
    expect($bin->fresh()->type)->toBe(LocationType::Bin);
});

it('kode lokasi unik per gudang', function () {
    $gudang = Warehouse::factory()->create();
    Location::factory()->for($gudang)->create(['code' => 'A-01']);

    expect(dalamSavepoint(fn () => Location::factory()->for($gudang)->create(['code' => 'A-01'])))
        ->toThrow(QueryException::class, 'locations_warehouse_id_code_unique');
    expect(Location::factory()->create(['code' => 'A-01'])->exists)->toBeTrue();
});

it('dokumen memakai enum tipe dan status serta punya baris', function () {
    $dokumen = Document::factory()->ofType(DocumentType::OUT)->create();
    DocumentLine::factory()->for($dokumen)->count(2)->create();

    $dokumen->refresh();
    expect($dokumen->type)->toBe(DocumentType::OUT);
    expect($dokumen->status)->toBe(DocumentStatus::Draft);
    expect($dokumen->lines)->toHaveCount(2);
    expect($dokumen->creator)->toBeInstanceOf(User::class);
});

it('menolak qty negatif pada baris dokumen', function (string $kolom) {
    expect(dalamSavepoint(fn () => DocumentLine::factory()->create([$kolom => -1])))
        ->toThrow(QueryException::class, 'document_lines_qty_non_negative');
})->with(['qty_planned', 'qty_scanned', 'qty_packed', 'qty_system']);
