<?php

use App\Enums\UserRole;

const MODE_SCAN = ['penerimaan' => 'Penerimaan', 'putaway' => 'Putaway', 'picking' => 'Picking',
    'packing' => 'Packing', 'transfer' => 'Transfer', 'opname' => 'Stock Opname'];

it('menu menampilkan 6 mode', function () {
    sebagai(UserRole::Staf);

    $respons = $this->get('/scan')->assertOk();

    foreach (MODE_SCAN as $mode => $label) {
        $respons->assertSee($label)->assertSee("/scan/{$mode}");
    }
});

it('setiap layar mode memuat kolom scan, kamera, dan komponennya', function (string $mode) {
    sebagai(UserRole::Staf);

    $this->get("/scan/{$mode}")->assertOk()
        ->assertSee('id="scan-input"', false)
        ->assertSee('x-data="'.$mode.'"', false)
        ->assertSee('Scan pakai kamera')
        ->assertSee('name="csrf-token"', false);
})->with(array_keys(MODE_SCAN));

it('halaman scan memakai layout sendiri tanpa navigasi Filament', function () {
    sebagai(UserRole::Staf);

    $this->get('/scan/picking')->assertOk()
        ->assertDontSee('fi-sidebar', false)
        ->assertDontSee('filament', false);
});

it('layar opname tidak memuat qty sistem', function () {
    sebagai(UserRole::Staf);

    $this->get('/scan/opname')->assertOk()
        ->assertSee('Hitung buta')
        ->assertDontSee('qty_system', false)
        ->assertDontSee('Qty sistem');
});

it('tombol utama berukuran minimal 44px', function () {
    sebagai(UserRole::Staf);

    $html = $this->get('/scan/penerimaan')->assertOk()->getContent();

    preg_match_all('/<button[^>]*class="([^"]*)"/', $html, $kelas);
    expect($kelas[1])->not->toBeEmpty();
    foreach ($kelas[1] as $class) {
        expect($class)->toMatch('/min-h-1[1-9]|min-h-\[4[4-9]px\]|size-11|h-1[1-9]/');
    }
});

it('mode tak dikenal 404 dan tamu diarahkan ke login', function () {
    $this->get('/scan/picking')->assertRedirect('/login');
    sebagai(UserRole::Staf);
    $this->get('/scan/sembarang')->assertNotFound();
});
