<?php

use App\Enums\UserRole;
use App\Filament\Resources\Items\ItemResource;
use App\Filament\Resources\Items\Pages\CreateItem;
use App\Filament\Resources\Items\Pages\EditItem;
use App\Filament\Resources\Locations\LocationResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\Warehouses\WarehouseResource;
use App\Models\ActivityLog;
use App\Models\DocumentLine;
use App\Models\Item;
use App\Models\ItemBarcode;
use App\Support\Ean13;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Repeater;
use Livewire\Livewire;

beforeEach(fn () => $this->batalFakeRepeater = Repeater::fake());
afterEach(fn () => ($this->batalFakeRepeater)());

/** @param list<array<string, mixed>> $barcodes */
function formBarang(array $barcodes, array $ubah = []): array
{
    return [
        'sku' => 'SKU-90001', 'name' => 'Teh Botol', 'category' => 'Minuman',
        'base_unit' => 'pcs', 'min_stock' => 12, 'is_active' => true,
        'barcodes' => $barcodes,
        ...$ubah,
    ];
}

function barcodeForm(string $barcode, string $type = 'Code 128', string $unit = 'pcs', int $factor = 1): array
{
    return ['barcode' => $barcode, 'type' => $type, 'unit' => $unit, 'factor' => $factor];
}

it('admin membuat barang dengan dua barcode', function () {
    sebagai(UserRole::Admin);
    $ean = Ean13::complete('899100099999');

    Livewire::test(CreateItem::class)
        ->fillForm(formBarang([barcodeForm($ean, 'EAN-13'), barcodeForm('DUS-SKU-90001', 'Code 128', 'dus', 24)]))
        ->call('create')
        ->assertHasNoFormErrors();

    $barang = Item::where('sku', 'SKU-90001')->sole();
    expect($barang->barcodes()->orderBy('factor')->pluck('factor', 'barcode')->all())
        ->toBe([$ean => 1, 'DUS-SKU-90001' => 24]);
});

it('barcode duplikat ditolak', function (array $barcodes, string $kolomGalat) {
    sebagai(UserRole::Admin);
    ItemBarcode::factory()->create(['barcode' => 'DUS-LAMA']);

    Livewire::test(CreateItem::class)
        ->fillForm(formBarang($barcodes))
        ->call('create')
        ->assertHasFormErrors([$kolomGalat]);

    expect(Item::where('sku', 'SKU-90001')->exists())->toBeFalse();
})->with([
    'sudah dipakai barang lain' => [[barcodeForm('DUS-LAMA')], 'barcodes.0.barcode'],
    'kembar dalam satu form' => [[barcodeForm('DUS-BARU'), barcodeForm('DUS-BARU')], 'barcodes.1.barcode'],
]);

it('menyimpan ulang barang dengan barcodenya sendiri tidak dianggap duplikat', function () {
    sebagai(UserRole::Admin);
    $barcode = ItemBarcode::factory()->dus(12)->create(['barcode' => 'DUS-TETAP']);

    Livewire::test(EditItem::class, ['record' => $barcode->item->getRouteKey()])
        ->fillForm(['name' => 'Nama Diubah'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($barcode->item->fresh()->name)->toBe('Nama Diubah');
});

it('barcode barang tidak boleh sama dengan barcode lokasi', function () {
    sebagai(UserRole::Admin);
    App\Models\Location::factory()->create(['barcode' => 'LOC-A-01']);

    Livewire::test(CreateItem::class)
        ->fillForm(formBarang([barcodeForm('LOC-A-01')]))
        ->call('create')
        ->assertHasFormErrors(['barcodes.0.barcode']);
});

it('barcode EAN-13 dengan checksum salah ditolak', function () {
    sebagai(UserRole::Admin);

    Livewire::test(CreateItem::class)
        ->fillForm(formBarang([barcodeForm('8991000999990', 'EAN-13')]))
        ->call('create')
        ->assertHasFormErrors(['barcodes.0.barcode']);
});

it('faktor konversi harus minimal 1', function () {
    sebagai(UserRole::Admin);

    Livewire::test(CreateItem::class)
        ->fillForm(formBarang([barcodeForm('DUS-NOL', 'Code 128', 'dus', 0)]))
        ->call('create')
        ->assertHasFormErrors(['barcodes.0.factor']);
});

it('supervisor hanya bisa melihat master data', function () {
    sebagai(UserRole::Supervisor);
    $barang = Item::factory()->create();

    $this->get(ItemResource::getUrl('index'))->assertOk();
    $this->get(LocationResource::getUrl('index'))->assertOk();
    $this->get(WarehouseResource::getUrl('index'))->assertOk();
    $this->get(ItemResource::getUrl('create'))->assertForbidden();
    $this->get(ItemResource::getUrl('edit', ['record' => $barang]))->assertForbidden();
    $this->get(UserResource::getUrl('index'))->assertForbidden();
});

it('admin bisa membuka semua halaman master', function (string $resource) {
    sebagai(UserRole::Admin);

    $this->get($resource::getUrl('index'))->assertOk();
    $this->get($resource::getUrl('create'))->assertOk();
})->with([ItemResource::class, LocationResource::class, WarehouseResource::class, UserResource::class]);

it('perubahan barang tercatat di activity_logs', function () {
    $admin = sebagai(UserRole::Admin);
    $barang = Item::factory()->create(['name' => 'Nama Lama']);

    Livewire::test(EditItem::class, ['record' => $barang->getRouteKey()])
        ->fillForm(['name' => 'Nama Baru'])
        ->call('save')
        ->assertHasNoFormErrors();

    $log = ActivityLog::where('action', 'barang.diubah')->sole();
    expect($log->user_id)->toBe($admin->id)
        ->and($log->subject_id)->toBe($barang->id)
        ->and($log->before)->toBe(['name' => 'Nama Lama'])
        ->and($log->after)->toBe(['name' => 'Nama Baru']);
});

it('pembuatan barcode tercatat di activity_logs', function () {
    sebagai(UserRole::Admin);

    Livewire::test(CreateItem::class)
        ->fillForm(formBarang([barcodeForm('DUS-LOG', 'Code 128', 'dus', 12)]))
        ->call('create');

    expect(ActivityLog::where('action', 'barang.dibuat')->count())->toBe(1);
    expect(ActivityLog::where('action', 'barcode.dibuat')->sole()->after)
        ->toMatchArray(['barcode' => 'DUS-LOG', 'factor' => 12]);
});

it('barang yang sudah dipakai transaksi tidak bisa dihapus', function () {
    sebagai(UserRole::Admin);
    $dipakai = Item::factory()->create();
    DocumentLine::factory()->create(['item_id' => $dipakai->id]);
    $belumDipakai = Item::factory()->create();

    Livewire::test(EditItem::class, ['record' => $dipakai->getRouteKey()])
        ->assertActionHidden(DeleteAction::class);
    Livewire::test(EditItem::class, ['record' => $belumDipakai->getRouteKey()])
        ->assertActionVisible(DeleteAction::class)
        ->callAction(DeleteAction::class);

    expect(Item::whereKey($belumDipakai->id)->exists())->toBeFalse();
});

it('pesan validasi berbahasa Indonesia', function () {
    sebagai(UserRole::Admin);

    Livewire::test(CreateItem::class)
        ->fillForm(formBarang([], ['name' => '']))
        ->call('create')
        ->assertHasFormErrors(['name' => 'required'])
        ->assertSee('Nama wajib diisi.');
});

it('staf tidak bisa membuka master data', function () {
    sebagai(UserRole::Staf);

    $this->get(ItemResource::getUrl('index'))->assertForbidden();
});
