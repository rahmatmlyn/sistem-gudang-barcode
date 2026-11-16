<?php

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\Item;
use App\Models\Location;
use App\Services\StockService;
use Database\Seeders\DatabaseSeeder;

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

function stokUji(): int
{
    $item = Item::where('sku', 'SKU-UJI-SCAN')->sole();

    return app(StockService::class)->available($item->id, Location::where('code', 'A-01-01')->sole()->id);
}

it('menyiapkan stok, dokumen OUT dirilis, dan token untuk skrip uji', function () {
    $this->artisan('gudang:siapkan-uji-scan', ['--stok' => 1, '--rencana' => 10])
        ->expectsOutputToContain('uji_scan_bersamaan.py race')
        ->assertSuccessful();

    $out = Document::ofType(DocumentType::OUT)->where('reference', 'UJI-SCAN')->sole();
    expect($out->status)->toBe(DocumentStatus::Proses)
        ->and($out->lines()->sole()->qty_planned)->toBe(10)
        ->and(stokUji())->toBe(1);
});

it('dijalankan ulang: dokumen uji lama dibatalkan dan stok diset ulang', function () {
    $this->artisan('gudang:siapkan-uji-scan', ['--stok' => 50, '--rencana' => 10])->assertSuccessful();
    $this->artisan('gudang:siapkan-uji-scan', ['--stok' => 3, '--rencana' => 10])->assertSuccessful();

    expect(Document::ofType(DocumentType::OUT)->where('reference', 'UJI-SCAN')->open()->count())->toBe(1)
        ->and(stokUji())->toBe(3);
});
