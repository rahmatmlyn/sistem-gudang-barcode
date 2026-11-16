<?php

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Filament\Pages\KartuStok;
use App\Filament\Pages\StokPerLokasi;
use App\Filament\Widgets\AktivitasScanTable;
use App\Filament\Widgets\SelisihOpnameTable;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\StokMinimumTable;
use App\Models\ScanLog;
use App\Reports\DashboardSummary;
use Livewire\Livewire;
use Tests\Support\Gudang;

beforeEach(function () {
    $this->g = Gudang::siapkan();
    $this->beras = $this->g->barang(['name' => 'Beras Premium 5 kg', 'min_stock' => 10]);
    $this->gula = $this->g->barang(['name' => 'Gula Pasir 1 kg', 'min_stock' => 0]);
    $this->g->barang(['is_active' => false]);
    $this->g->stokAwal($this->beras, $this->g->binA, 5);
    $this->g->stokAwal($this->gula, $this->g->binA, 40);
    $this->g->dokumen(DocumentType::GRN);
    $this->g->dokumen(DocumentType::OUT, [], ['status' => DocumentStatus::Proses]);
    $this->opn = $this->g->opnameDiajukan([[$this->gula, 42]]);
});

it('ringkasan dashboard menghitung empat kartu dengan data terkontrol', function () {
    expect(DashboardSummary::get())->toBe([
        'sku_aktif' => 2, 'di_bawah_minimum' => 1, 'dokumen_berjalan' => 3, 'menunggu_approval' => 1,
    ]);
});

it('widget dashboard tampil untuk supervisor', function () {
    sebagai(UserRole::Supervisor);
    ScanLog::create(['user_id' => $this->g->staf->id, 'mode' => 'GRN', 'barcode' => 'X', 'success' => true, 'message' => 'Diterima +1 pcs Teh Botol', 'response' => []]);

    Livewire::test(StatsOverview::class)->assertSee('SKU aktif')->assertSee('Selisih opname menunggu approval');
    Livewire::test(SelisihOpnameTable::class)->assertSee('Gula Pasir 1 kg')->assertSee('A-01-01');
    Livewire::test(StokMinimumTable::class)->assertSee('Beras Premium 5 kg')->assertDontSee('Gula Pasir 1 kg');
    Livewire::test(AktivitasScanTable::class)->assertSee('Diterima +1 pcs Teh Botol');
    $this->get('/admin')->assertOk();
});

it('halaman kartu stok menampilkan saldo dan bisa diexport', function () {
    sebagai(UserRole::Supervisor);

    Livewire::test(KartuStok::class)
        ->fillForm(['item_id' => $this->beras->id, 'dari' => now()->subDay()->toDateString(), 'sampai' => now()->toDateString()])
        ->assertSee('Saldo akhir')
        ->assertCanSeeTableRecords($this->beras->movements()->get())
        ->callAction('exportExcel')
        ->assertFileDownloaded()
        ->callAction('exportPdf')
        ->assertFileDownloaded();
});

it('halaman stok per lokasi tersaring dan bisa diexport', function () {
    sebagai(UserRole::Admin);

    Livewire::test(StokPerLokasi::class)
        ->fillForm(['item_id' => $this->gula->id])
        ->assertSee('Gula Pasir 1 kg')
        ->assertDontSee('Beras Premium 5 kg')
        ->callAction('exportExcel')
        ->assertFileDownloaded();
});

it('staf tidak bisa membuka laporan', function () {
    sebagai(UserRole::Staf);

    $this->get(KartuStok::getUrl())->assertForbidden();
    $this->get(StokPerLokasi::getUrl())->assertForbidden();
});
