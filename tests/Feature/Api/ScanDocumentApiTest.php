<?php

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\OpnameLocationStatus;
use App\Models\Document;
use App\Models\ItemBarcode;
use App\Services\OpnameService;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Gudang;

beforeEach(function () {
    $this->g = Gudang::siapkan();
    $this->barang = $this->g->barang(['sku' => 'SKU-00001']);
    ItemBarcode::factory()->create(['item_id' => $this->barang->id, 'barcode' => 'BC-00001']);
});

it('tanpa token 401, token staf bisa scan', function () {
    $grn = $this->g->dokumen(DocumentType::GRN);
    $body = ['type' => 'GRN', 'document_no' => $grn->number, 'barcode' => 'BC-00001'];

    $this->postJson('/api/scan', $body)->assertUnauthorized();
    $token = $this->g->staf->createToken('uji')->plainTextToken;
    $this->withToken($token)->postJson('/api/scan', $body)->assertOk();
});

it('daftar dokumen terbuka per tipe untuk halaman scan', function () {
    Sanctum::actingAs($this->g->staf);
    $draft = $this->g->dokumen(DocumentType::GRN);
    $this->g->dokumen(DocumentType::GRN, [], ['status' => DocumentStatus::Selesai]);
    $this->g->dokumen(DocumentType::OUT, [['item' => $this->barang, 'planned' => 1]]);

    $this->getJson('/api/scan/documents?type=GRN')->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.nomor', $draft->number);
    $this->getJson('/api/scan/documents?type=OUT')->assertOk()->assertJsonCount(0, 'data');
});

it('staf membuat GRN dari halaman scan', function () {
    Sanctum::actingAs($this->g->staf);

    $this->postJson('/api/scan/documents', ['type' => 'GRN', 'partner' => 'PT Sumber', 'reference' => 'PO-1'])
        ->assertCreated()
        ->assertJsonPath('data.nomor', sprintf('GRN-%d-00001', now()->year));

    expect(Document::sole())->created_by->toBe($this->g->staf->id)->warehouse_id->toBe($this->g->gudang->id);
});

it('staf tidak bisa membuat pesanan keluar dari halaman scan', function () {
    Sanctum::actingAs($this->g->staf);

    $this->postJson('/api/scan/documents', ['type' => 'OUT'])->assertUnprocessable();
});

it('tombol − dan + mengubah qty baris penerimaan', function () {
    Sanctum::actingAs($this->g->staf);
    $grn = $this->g->dokumen(DocumentType::GRN, [['item' => $this->barang, 'scanned' => 5]]);
    $baris = $grn->lines->first();

    $this->patchJson("/api/scan/lines/{$baris->id}", ['delta' => -1])->assertOk()->assertJsonPath('baris.qty_scan', 4);
    $this->patchJson("/api/scan/lines/{$baris->id}", ['delta' => 2])->assertOk()->assertJsonPath('baris.qty_scan', 6);
    $this->patchJson("/api/scan/lines/{$baris->id}", ['delta' => -7])->assertUnprocessable();
});

it('selesai penerimaan lalu putaway berisi baris GRN', function () {
    Sanctum::actingAs($this->g->staf);
    $grn = $this->g->dokumen(DocumentType::GRN, [['item' => $this->barang, 'scanned' => 12]]);

    $this->postJson("/api/scan/documents/{$grn->number}/complete")->assertOk();
    $nomor = $this->postJson("/api/scan/documents/{$grn->number}/putaway")->assertCreated()->json('data.nomor');

    $put = Document::where('number', $nomor)->sole();
    expect($put)->type->toBe(DocumentType::PUT)->related_document_id->toBe($grn->id);
    expect($put->lines()->sole())->from_location_id->toBe($this->g->rcv->id)->qty_planned->toBe(12);
    expect(Gudang::saldo($this->barang, $this->g->rcv))->toBe(12);
});

it('detail dokumen picking urut lokasi', function () {
    Sanctum::actingAs($this->g->staf);
    $out = $this->g->dokumen(DocumentType::OUT, [
        ['item' => $this->barang, 'from' => $this->g->binB, 'planned' => 1],
        ['item' => $this->g->barang(), 'from' => $this->g->binA, 'planned' => 2],
    ], ['status' => DocumentStatus::Proses]);

    $this->getJson("/api/scan/documents/{$out->number}")->assertOk()
        ->assertJsonPath('data.baris.0.lokasi_asal', 'A-01-01')
        ->assertJsonPath('data.baris.1.lokasi_asal', 'B-01-01');
});

it('opname: selesai per lokasi lalu ajukan, tanpa qty sistem di respons', function () {
    Sanctum::actingAs($this->g->staf);
    $this->g->stokAwal($this->barang, $this->g->binA, 37);
    $opn = $this->g->dokumen(DocumentType::OPN);
    app(OpnameService::class)->setLocations($opn, [$this->g->binA->id, $this->g->binB->id]);

    $this->postJson("/api/scan/opname/{$opn->number}/locations/A-01-01", ['kosong' => false])->assertOk();
    $this->postJson("/api/scan/opname/{$opn->number}/locations/B-01-01", ['kosong' => true])->assertOk();
    $detail = $this->getJson("/api/scan/documents/{$opn->number}")->assertOk();
    $this->postJson("/api/scan/documents/{$opn->number}/complete")->assertOk();

    assertTanpaQtySistem($detail->json(), 37);
    expect($opn->fresh()->submitted_at)->not->toBeNull()
        ->and($opn->opnameLocations()->where('status', OpnameLocationStatus::Belum)->count())->toBe(0);
});

it('aksi dokumen yang melanggar aturan menjawab JSON dengan kode', function () {
    Sanctum::actingAs($this->g->staf);
    $grn = $this->g->dokumen(DocumentType::GRN);

    $this->postJson("/api/scan/documents/{$grn->number}/complete")
        ->assertUnprocessable()->assertJson(['status' => 'gagal', 'kode' => 'QTY_TIDAK_COCOK']);
});
