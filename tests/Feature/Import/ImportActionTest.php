<?php

use App\Enums\UserRole;
use App\Filament\Resources\Items\Pages\ListItems;
use App\Filament\Resources\Locations\Pages\ListLocations;
use App\Models\Item;
use App\Models\Location;
use App\Models\Warehouse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(fn () => Storage::fake('local'));

function unggah(string $nama, string $isi): UploadedFile
{
    return UploadedFile::fake()->createWithContent($nama, $isi);
}

it('admin mengimport barang dan bisa mengunduh laporan baris gagal (UAT-18)', function () {
    sebagai(UserRole::Admin);
    $csv = "sku,name,category,base_unit,min_stock\nSKU-00001,Gula,Sembako,kg,5\n,Tanpa SKU,,pcs,0\n";

    Livewire::test(ListItems::class)
        ->callAction('importBarang', data: ['file' => unggah('barang.csv', $csv)])
        ->assertHasNoActionErrors()
        ->assertNotified('Import selesai dengan baris gagal');

    expect(Item::count())->toBe(1);
    $laporan = Storage::disk('local')->files('laporan-import');
    expect($laporan)->toHaveCount(1);
    expect(Storage::disk('local')->files('imports'))->toBe([]);

    $respons = $this->get(route('laporan-import.unduh', basename($laporan[0], '.csv')));
    $respons->assertOk()->assertDownload();
    expect($respons->streamedContent())->toBe("baris,pesan\n3,\"SKU wajib diisi.\"\n");
});

it('import tanpa baris gagal memberi notifikasi sukses tanpa laporan', function () {
    sebagai(UserRole::Admin);
    Warehouse::factory()->create(['code' => 'GU']);
    $csv = "code,warehouse,type,parent,barcode\nA,GU,zona,GU,LOC-A\n";

    Livewire::test(ListLocations::class)
        ->callAction('importLokasi', data: ['file' => unggah('lokasi.csv', $csv)])
        ->assertNotified('Import selesai');

    expect(Location::where('code', 'A')->exists())->toBeTrue();
    expect(Storage::disk('local')->files('laporan-import'))->toBe([]);
});

it('menolak file selain csv atau xlsx', function () {
    sebagai(UserRole::Admin);

    Livewire::test(ListItems::class)
        ->callAction('importBarang', data: ['file' => unggah('barang.pdf', '%PDF-1.4')])
        ->assertHasActionErrors(['file']);
});

it('supervisor tidak melihat tombol import', function () {
    sebagai(UserRole::Supervisor);

    Livewire::test(ListItems::class)
        ->assertActionHidden('importBarang')
        ->assertActionHidden('importBarcode');
});

it('laporan import hanya bisa diunduh admin', function () {
    Storage::disk('local')->put('laporan-import/0b6a4c2e-8f4e-4c4b-9a8e-1f2d3c4b5a69.csv', "baris,pesan\n");
    sebagai(UserRole::Supervisor);

    $this->get(route('laporan-import.unduh', '0b6a4c2e-8f4e-4c4b-9a8e-1f2d3c4b5a69'))->assertForbidden();
});
