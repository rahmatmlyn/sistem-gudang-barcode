<?php

use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\StokMinimumNotification;
use App\Services\StockService;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Gudang;

beforeEach(function () {
    Notification::fake();
    $this->g = Gudang::siapkan();
    $this->admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->barang = $this->g->barang(['min_stock' => 10]);
});

function kirim($test, int $qty): void
{
    $out = $test->g->dokumen(DocumentType::OUT, [['item' => $test->barang, 'from' => $test->g->binA, 'planned' => $qty, 'scanned' => $qty, 'packed' => $qty]]);
    app(StockService::class)->issue($out, $test->g->staf);
}

it('notifikasi terkirim sekali saat stok mencapai minimum (UAT-22)', function () {
    $this->g->stokAwal($this->barang, $this->g->binA, 15);
    Notification::assertNothingSent();

    kirim($this, 5);
    kirim($this, 1);

    Notification::assertSentToTimes($this->admin, StokMinimumNotification::class, 1);
    Notification::assertNotSentTo($this->g->staf, StokMinimumNotification::class);
});

it('notifikasi dikirim lagi setelah stok pulih lalu turun lagi', function () {
    $this->g->stokAwal($this->barang, $this->g->binA, 15);
    kirim($this, 6);
    $this->g->stokAwal($this->barang, $this->g->binA, 20);
    kirim($this, 20);

    Notification::assertSentToTimes($this->admin, StokMinimumNotification::class, 2);
});

it('barang tanpa stok minimum tidak memicu notifikasi', function () {
    $this->barang->update(['min_stock' => 0]);
    $this->g->stokAwal($this->barang, $this->g->binA, 3);
    kirim($this, 3);

    Notification::assertNothingSent();
});

it('notifikasi memakai kanal database dan email', function () {
    $this->g->stokAwal($this->barang, $this->g->binA, 15);
    kirim($this, 5);

    Notification::assertSentTo($this->admin, StokMinimumNotification::class,
        fn (StokMinimumNotification $notifikasi, array $kanal) => $kanal === ['database', 'mail'] && $notifikasi->qty === 10);
});
