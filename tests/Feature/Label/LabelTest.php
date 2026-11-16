<?php

use App\Enums\UserRole;
use App\Exceptions\LabelTidakValidException;
use App\Filament\Resources\Items\Pages\ListItems;
use App\Filament\Resources\Locations\Pages\ListLocations;
use App\Models\Item;
use App\Models\ItemBarcode;
use App\Models\Location;
use App\Services\LabelService;
use App\Support\Ean13;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/** @return array{halaman: int, ukuran: list<float>} */
function infoPdf(string $pdf): array
{
    preg_match_all('#/Type\s*/Page[^s]#', $pdf, $halaman);
    preg_match('#/MediaBox\s*\[\s*([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)\s*\]#', $pdf, $kotak);

    return ['halaman' => count($halaman[0]), 'ukuran' => [round((float) $kotak[3], 2), round((float) $kotak[4], 2)]];
}

function barangBerlabel(string $sku): Item
{
    $barang = Item::factory()->create(['sku' => $sku, 'name' => "Barang {$sku}"]);
    ItemBarcode::factory()->create(['item_id' => $barang->id, 'barcode' => Ean13::complete(sprintf('8991%08d', crc32($sku) % 100000000)), 'type' => 'EAN-13']);
    ItemBarcode::factory()->dus(24)->create(['item_id' => $barang->id, 'barcode' => "DUS-{$sku}", 'type' => 'Code 128']);

    return $barang;
}

it('pdf label berukuran 50x30 mm dan satu halaman per label', function () {
    $barcodes = collect([barangBerlabel('SKU-A'), barangBerlabel('SKU-B')])->flatMap->barcodes;

    $pdf = app(LabelService::class)->itemPdf($barcodes, copies: 2);

    expect($pdf)->toStartWith('%PDF')
        ->and(infoPdf($pdf))->toBe(['halaman' => 8, 'ukuran' => [141.73, 85.04]]);
});

it('label lokasi satu halaman per lokasi', function () {
    $lokasi = Location::factory()->count(3)->create();

    expect(infoPdf(app(LabelService::class)->locationPdf($lokasi))['halaman'])->toBe(3);
});

it('barcode QR dicetak sebagai gambar QR', function () {
    $qr = ItemBarcode::factory()->create(['barcode' => 'QR-ISI-123', 'type' => 'QR']);

    $label = app(LabelService::class)->itemLabels(collect([$qr]))[0];

    expect($label['gambar'])->toStartWith('data:image/png;base64,')
        ->and($label['jenis'])->toBe('QR');
});

it('EAN-13 tidak valid ditolak dengan pesan', function () {
    $salah = ItemBarcode::factory()->create(['barcode' => 'DUMMY', 'type' => 'Code 128']);
    DB::table('item_barcodes')->where('id', $salah->id)->update(['barcode' => '8991000000011', 'type' => 'EAN-13']);

    expect(fn () => app(LabelService::class)->itemPdf(collect([$salah->fresh()])))
        ->toThrow(LabelTidakValidException::class, '8991000000011');
});

it('aksi massal cetak label di daftar barang dan lokasi', function () {
    sebagai(UserRole::Admin);
    $barang = barangBerlabel('SKU-C');
    $lokasi = Location::factory()->create();

    Livewire::test(ListItems::class)
        ->callTableBulkAction('cetakLabel', [$barang], data: ['jumlah' => 1])
        ->assertFileDownloaded();
    Livewire::test(ListLocations::class)
        ->callTableBulkAction('cetakLabel', [$lokasi], data: ['jumlah' => 2])
        ->assertFileDownloaded();
});

it('cetak label dengan EAN-13 tidak valid menampilkan pesan, bukan error', function () {
    sebagai(UserRole::Admin);
    $salah = ItemBarcode::factory()->create(['barcode' => 'DUMMY2', 'type' => 'Code 128']);
    DB::table('item_barcodes')->where('id', $salah->id)->update(['barcode' => '8991000000011', 'type' => 'EAN-13']);

    Livewire::test(ListItems::class)
        ->callTableBulkAction('cetakLabel', [$salah->item], data: ['jumlah' => 1])
        ->assertNotified('Label tidak bisa dicetak');
});

it('cetak lebih dari batas label per PDF ditolak dengan pesan', function () {
    $lokasi = Location::factory()->count(2)->create();

    expect(fn () => app(LabelService::class)->locationPdf($lokasi, copies: LabelService::MAX_LABELS))
        ->toThrow(LabelTidakValidException::class, 'Terlalu banyak label');
});
