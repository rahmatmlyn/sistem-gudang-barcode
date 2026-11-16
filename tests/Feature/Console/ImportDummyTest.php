<?php

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\Item;
use App\Models\Location;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\DocumentNumberService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\Gudang;

beforeEach(fn () => $this->artisan('gudang:import-dummy', ['--path' => 'tests/Fixtures/dummy'])->assertSuccessful());

it('import mini lalu verifikasi lulus', function () {
    expect([User::count(), Location::count(), Item::count(), StockMovement::count(), DB::table('stock_balances')->count()])
        ->toBe([3, 5, 2, 9, 4]);
    expect(User::where('username', 'spv01')->sole())->role->toBe(UserRole::Supervisor)
        ->and(Hash::check('password', User::where('username', 'staf01')->value('password')))->toBeTrue();
    expect(Location::with('parent.parent')->where('code', 'A-01-01')->sole()->path)->toBe('A › A-01 › A-01-01');

    $this->artisan('gudang:verifikasi-saldo')->expectsOutputToContain('cocok')->assertSuccessful();
    Gudang::assertLedgerCocok();
});

it('dokumen selesai dibuat per nomor dan tipe, putaway di bawah GRN menjadi dokumen PUT', function () {
    $grn = Document::where('number', 'GRN-2026-00001')->sole();
    $put = Document::ofType(DocumentType::PUT)->sole();

    expect($grn)->status->toBe(DocumentStatus::Selesai)->type->toBe(DocumentType::GRN)
        ->and($grn->completed_at->equalTo(Carbon::parse('2026-01-02 08:01:00')))->toBeTrue();
    expect($put)->number->toBe('PUT-2026-00001')->related_document_id->toBe($grn->id)->reference->toBe('GRN-2026-00001');
    expect($put->lines()->where('item_id', Item::where('sku', 'SKU-00002')->value('id'))->sole()->qty_scanned)->toBe(30);
    expect(StockMovement::where('qty', 10)->sole()->created_at->equalTo(Carbon::parse('2026-01-02 09:02:00')))->toBeTrue();
    expect(Document::where('number', 'ADJ-2026-00001')->sole()->approver->username)->toBe('spv01');
});

it('nomor dokumen berikutnya melanjutkan nomor terbesar hasil import', function () {
    $tahun2026 = Carbon::parse('2026-06-01');

    expect(app(DocumentNumberService::class)->next(DocumentType::GRN, $tahun2026))->toBe('GRN-2026-00003')
        ->and(app(DocumentNumberService::class)->next(DocumentType::PUT, $tahun2026))->toBe('PUT-2026-00002');
});

it('verifikasi gagal bila saldo dimanipulasi', function () {
    DB::table('stock_balances')->where('qty', 36)->update(['qty' => 40]);

    $this->artisan('gudang:verifikasi-saldo')
        ->expectsOutputToContain('SKU-00001')
        ->assertFailed();
});

it('menolak import ulang tanpa --fresh', function () {
    $this->artisan('gudang:import-dummy', ['--path' => 'tests/Fixtures/dummy'])
        ->expectsOutputToContain('--fresh')
        ->assertFailed();

    expect(StockMovement::count())->toBe(9);
});
