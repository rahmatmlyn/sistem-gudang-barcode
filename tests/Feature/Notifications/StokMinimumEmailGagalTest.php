<?php

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\StockService;
use Tests\Support\Gudang;

it('server email mati tidak menggagalkan penyelesaian dokumen; notifikasi dalam aplikasi tetap masuk', function () {
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 1]);
    $g = Gudang::siapkan();
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $barang = $g->barang(['min_stock' => 10]);
    $g->stokAwal($barang, $g->binA, 15);
    $out = $g->dokumen(DocumentType::OUT, [['item' => $barang, 'from' => $g->binA, 'planned' => 6, 'scanned' => 6, 'packed' => 6]]);

    app(StockService::class)->issue($out, $g->staf);

    expect($out->fresh()->status)->toBe(DocumentStatus::Selesai)
        ->and($admin->notifications()->count())->toBe(1);
});
