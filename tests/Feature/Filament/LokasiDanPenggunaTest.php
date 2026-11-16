<?php

use App\Enums\LocationType;
use App\Enums\UserRole;
use App\Filament\Resources\Locations\Pages\CreateLocation;
use App\Filament\Resources\Locations\Pages\EditLocation;
use App\Filament\Resources\Locations\Pages\ListLocations;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\ActivityLog;
use App\Models\Location;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

/** @return array{0: Warehouse, 1: Location, 2: Location, 3: Location} gudang, zona A, rak A-01, bin A-01-02 */
function pohonLokasi(): array
{
    $gudang = Warehouse::factory()->create(['code' => 'GU']);
    $buat = fn (string $code, LocationType $type, ?Location $parent) => Location::factory()->create([
        'warehouse_id' => $gudang->id, 'code' => $code, 'type' => $type,
        'parent_id' => $parent?->id, 'barcode' => 'LOC-'.$code,
    ]);
    $zona = $buat('A', LocationType::Zona, null);
    $rak = $buat('A-01', LocationType::Rak, $zona);

    return [$gudang, $zona, $rak, $buat('A-01-02', LocationType::Bin, $rak)];
}

it('lokasi tidak boleh menjadi parent dirinya sendiri', function () {
    sebagai(UserRole::Admin);
    [, $zona] = pohonLokasi();

    Livewire::test(EditLocation::class, ['record' => $zona->getRouteKey()])
        ->fillForm(['parent_id' => $zona->id])
        ->call('save')
        ->assertHasFormErrors(['parent_id']);

    expect($zona->fresh()->parent_id)->toBeNull();
});

it('lokasi tidak boleh berparent turunannya sendiri', function () {
    sebagai(UserRole::Admin);
    [, $zona, , $bin] = pohonLokasi();

    Livewire::test(EditLocation::class, ['record' => $zona->getRouteKey()])
        ->fillForm(['parent_id' => $bin->id])
        ->call('save')
        ->assertHasFormErrors(['parent_id']);
});

it('parent harus di gudang yang sama', function () {
    sebagai(UserRole::Admin);
    [$gudang, $zona] = pohonLokasi();
    $gudangLain = Warehouse::factory()->create();

    Livewire::test(CreateLocation::class)
        ->fillForm(['warehouse_id' => $gudangLain->id, 'type' => 'rak', 'code' => 'Z-01', 'parent_id' => $zona->id])
        ->call('create')
        ->assertHasFormErrors(['parent_id']);
});

it('barcode lokasi default LOC-kode', function () {
    sebagai(UserRole::Admin);
    [$gudang, $zona] = pohonLokasi();

    Livewire::test(CreateLocation::class)
        ->fillForm(['warehouse_id' => $gudang->id, 'type' => 'rak', 'code' => 'A-02', 'parent_id' => $zona->id])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Location::where('code', 'A-02')->sole()->barcode)->toBe('LOC-A-02');
});

it('tabel lokasi menampilkan path hierarki', function () {
    sebagai(UserRole::Supervisor);
    pohonLokasi();

    Livewire::test(ListLocations::class)->assertSee('A › A-01 › A-01-02');
});

it('admin membuat pengguna staf yang bisa login', function () {
    sebagai(UserRole::Admin);

    Livewire::test(CreateUser::class)
        ->fillForm(['username' => 'staf99', 'name' => 'Staf 99', 'role' => 'staf', 'password' => 'rahasia123'])
        ->call('create')
        ->assertHasNoFormErrors();

    $staf = User::where('username', 'staf99')->sole();
    expect($staf->role)->toBe(UserRole::Staf)
        ->and(Hash::check('rahasia123', $staf->password))->toBeTrue();
});

it('log perubahan pengguna tidak memuat password', function () {
    sebagai(UserRole::Admin);

    Livewire::test(CreateUser::class)
        ->fillForm(['username' => 'staf98', 'name' => 'Staf 98', 'role' => 'staf', 'password' => 'rahasia123'])
        ->call('create');

    $log = ActivityLog::where('action', 'pengguna.dibuat')
        ->where('subject_id', User::where('username', 'staf98')->value('id'))
        ->sole();
    expect($log->after)->toHaveKey('username')
        ->not->toHaveKey('password')
        ->not->toHaveKey('remember_token');
});
