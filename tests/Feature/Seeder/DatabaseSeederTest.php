<?php

use App\Enums\LocationType;
use App\Enums\UserRole;
use App\Models\Item;
use App\Models\ItemBarcode;
use App\Models\Location;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Ean13;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

it('membuat satu gudang dengan area terima, area kirim, dan lokasi bertingkat', function () {
    $gudang = Warehouse::sole();
    expect($gudang->code)->toBe('GU');

    $jumlahPerTipe = Location::query()->get()->countBy(fn (Location $lokasi) => $lokasi->type->value)->all();
    expect($jumlahPerTipe)->toEqual([
        LocationType::AreaTerima->value => 1,
        LocationType::AreaKirim->value => 1,
        LocationType::Zona->value => 2,
        LocationType::Rak->value => 4,
        LocationType::Bin->value => 12,
    ]);

    $bin = Location::where('code', 'A-01-02')->sole();
    expect($bin->barcode)->toBe('LOC-A-01-02');
    expect($bin->parent->code)->toBe('A-01');
    expect($bin->parent->parent->code)->toBe('A');
});

it('membuat user admin, supervisor, dan staf01 dengan password default', function () {
    $peran = User::query()->pluck('role', 'username')->map->value->all();

    expect($peran)->toEqual([
        'admin' => UserRole::Admin->value,
        'supervisor' => UserRole::Supervisor->value,
        'staf01' => UserRole::Staf->value,
    ]);
    expect(Hash::check('password', User::where('username', 'staf01')->sole()->password))->toBeTrue();
});

it('membuat 20 barang dengan barcode EAN-13 valid dan sebagian punya barcode dus', function () {
    expect(Item::count())->toBe(20);

    $satuan = ItemBarcode::where('factor', 1)->get();
    expect($satuan)->toHaveCount(20);
    expect($satuan->every(fn (ItemBarcode $barcode) => Ean13::isValid($barcode->barcode)))->toBeTrue();

    $dus = ItemBarcode::where('factor', '>', 1)->get();
    expect($dus)->not->toBeEmpty();
    expect($dus->every(fn (ItemBarcode $barcode) => $barcode->unit === 'dus'))->toBeTrue();
});

it('tidak membuat saldo atau movement (stok hanya lewat StockService)', function () {
    expect(StockBalance::count())->toBe(0);
    expect(StockMovement::count())->toBe(0);
});

it('aman dijalankan dua kali', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Location::count())->toBe(20);
    expect(Item::count())->toBe(20);
    expect(User::count())->toBe(3);
});
