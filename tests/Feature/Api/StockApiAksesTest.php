<?php

use App\Models\ItemBarcode;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Gudang;

beforeEach(function () {
    $this->g = Gudang::siapkan();
    ItemBarcode::factory()->create(['barcode' => 'BC-00001']);
    $this->url = '/api/stock?barcode=BC-00001&location=A-01-01';
});

it('sesi halaman scan tidak bisa membaca stok (hitung buta opname)', function () {
    $this->actingAs($this->g->staf)->getJson($this->url)->assertForbidden();
});

it('token tanpa ability uji-scan ditolak, termasuk token wildcard', function () {
    $this->withToken($this->g->staf->createToken('lain')->plainTextToken)->getJson($this->url)->assertForbidden();
    Sanctum::actingAs($this->g->supervisor, ['*']);
    $this->getJson($this->url)->assertForbidden();
});

it('token uji-scan bisa membaca stok', function () {
    $token = $this->g->staf->createToken('uji-scan', ['uji-scan'])->plainTextToken;

    $this->withToken($token)->getJson($this->url)->assertOk()->assertExactJson(['qty' => 0, 'saldo' => 0, 'dipesan' => 0]);
});
