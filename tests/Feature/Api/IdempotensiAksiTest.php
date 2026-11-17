<?php

use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\ItemBarcode;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Gudang;

beforeEach(function () {
    $this->g = Gudang::siapkan();
    $this->barang = $this->g->barang();
    ItemBarcode::factory()->create(['item_id' => $this->barang->id, 'barcode' => 'BC-00001']);
    Sanctum::actingAs($this->g->staf);
});

it('tombol + yang dikirim ulang dengan Idempotency-Key sama hanya dihitung sekali', function () {
    $baris = $this->g->dokumen(DocumentType::GRN, [['item' => $this->barang, 'scanned' => 5]])->lines->first();
    $kunci = ['Idempotency-Key' => '7d3f1c2a-1111-4c3d-9e8f-0a1b2c3d4e5f'];

    $pertama = $this->patchJson("/api/scan/lines/{$baris->id}", ['delta' => 1], $kunci)->assertOk()->json();
    $kedua = $this->patchJson("/api/scan/lines/{$baris->id}", ['delta' => 1], $kunci)->assertOk()->json();
    $this->patchJson("/api/scan/lines/{$baris->id}", ['delta' => 1], ['Idempotency-Key' => '7d3f1c2a-2222-4c3d-9e8f-0a1b2c3d4e5f'])->assertOk();

    expect($kedua)->toEqual($pertama)->and($baris->fresh()->qty_scanned)->toBe(7);
});

it('buat GRN yang dikirim ulang tidak membuat dokumen ganda', function () {
    $kunci = ['Idempotency-Key' => '7d3f1c2a-3333-4c3d-9e8f-0a1b2c3d4e5f'];

    $pertama = $this->postJson('/api/scan/documents', ['type' => 'GRN'], $kunci)->assertCreated()->json('data.nomor');
    $kedua = $this->postJson('/api/scan/documents', ['type' => 'GRN'], $kunci)->assertCreated()->json('data.nomor');

    expect($kedua)->toBe($pertama)->and(Document::count())->toBe(1);
});

it('respons gagal juga diputar ulang tanpa menjalankan aksi lagi', function () {
    $grn = $this->g->dokumen(DocumentType::GRN);
    $kunci = ['Idempotency-Key' => '7d3f1c2a-4444-4c3d-9e8f-0a1b2c3d4e5f'];

    $this->postJson("/api/scan/documents/{$grn->number}/complete", [], $kunci)->assertUnprocessable()->assertJson(['kode' => 'QTY_TIDAK_COCOK']);
    $this->g->baris($grn, ['item' => $this->barang, 'scanned' => 3]);
    $this->postJson("/api/scan/documents/{$grn->number}/complete", [], $kunci)->assertUnprocessable();

    expect($grn->fresh()->status->value)->toBe('draft');
});

it('kunci yang sama dari pengguna lain tidak memutar ulang respons orang lain', function () {
    $kunci = ['Idempotency-Key' => '7d3f1c2a-5555-4c3d-9e8f-0a1b2c3d4e5f'];
    $this->postJson('/api/scan/documents', ['type' => 'GRN'], $kunci)->assertCreated();

    Sanctum::actingAs($this->g->supervisor);
    $this->postJson('/api/scan/documents', ['type' => 'GRN'], $kunci)->assertCreated();

    expect(Document::count())->toBe(2);
});

it('Idempotency-Key yang bukan UUID ditolak', function () {
    $this->postJson('/api/scan/documents', ['type' => 'GRN'], ['Idempotency-Key' => 'abc'])->assertUnprocessable();
});

it('tombol + penerimaan tidak boleh melebihi rencana GRN', function () {
    $baris = $this->g->dokumen(DocumentType::GRN, [['item' => $this->barang, 'planned' => 2, 'scanned' => 2]])->lines->first();

    $this->patchJson("/api/scan/lines/{$baris->id}", ['delta' => 1])->assertUnprocessable()->assertJson(['kode' => 'MELEBIHI_PESANAN']);

    expect($baris->fresh()->qty_scanned)->toBe(2);
});

it('kosong false tanpa delta dijawab 422, bukan 500', function () {
    $baris = $this->g->dokumen(DocumentType::GRN, [['item' => $this->barang, 'scanned' => 1]])->lines->first();

    $this->patchJson("/api/scan/lines/{$baris->id}", ['kosong' => false])->assertUnprocessable()->assertJsonValidationErrors('delta');
});
