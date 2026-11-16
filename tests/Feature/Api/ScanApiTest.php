<?php

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\ItemBarcode;
use App\Models\ScanLog;
use App\Services\OpnameService;
use App\Support\Ean13;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Gudang;

beforeEach(function () {
    $this->g = Gudang::siapkan();
    $this->barang = $this->g->barang(['sku' => 'SKU-00001', 'name' => 'Mi Instan']);
    $this->ean = ItemBarcode::factory()->create(['item_id' => $this->barang->id, 'barcode' => Ean13::complete('899100000001'), 'type' => 'EAN-13']);
    $this->dus = ItemBarcode::factory()->dus(40)->create(['item_id' => $this->barang->id, 'barcode' => 'DUS-SKU-00001']);
    Sanctum::actingAs($this->g->staf);
});

function scan(array $body)
{
    return test()->postJson('/api/scan', $body);
}

it('scan barang terdaftar menambah qty 1 (UAT-01)', function () {
    $grn = $this->g->dokumen(DocumentType::GRN);

    scan(['type' => 'GRN', 'document_no' => $grn->number, 'barcode' => $this->ean->barcode])
        ->assertOk()
        ->assertJson(['status' => 'ok', 'jenis' => 'barang', 'item' => ['sku' => 'SKU-00001'], 'qty_dasar' => 1, 'baris' => ['qty_scan' => 1]]);

    expect($grn->lines()->sole()->qty_scanned)->toBe(1)
        ->and($grn->fresh()->status)->toBe(DocumentStatus::Proses);
});

it('barcode tak dikenal 404 dan tidak ada baris tersimpan (UAT-02)', function () {
    $grn = $this->g->dokumen(DocumentType::GRN);

    scan(['type' => 'GRN', 'document_no' => $grn->number, 'barcode' => 'TIDAK-ADA'])
        ->assertNotFound()
        ->assertJson(['status' => 'gagal', 'kode' => 'BARCODE_TIDAK_DIKENAL']);

    expect($grn->lines()->count())->toBe(0);
    expect(ScanLog::sole())->success->toBeFalse()->code->toBe('BARCODE_TIDAK_DIKENAL');
});

it('scan dus menambah qty sesuai faktor (UAT-03)', function () {
    $grn = $this->g->dokumen(DocumentType::GRN);

    scan(['type' => 'GRN', 'document_no' => $grn->number, 'barcode' => 'DUS-SKU-00001', 'qty' => 2])
        ->assertOk()
        ->assertJson(['qty_dasar' => 80, 'kemasan' => ['satuan' => 'dus', 'faktor' => 40], 'baris' => ['qty_scan' => 80]]);
});

it('penerimaan melebihi rencana ditolak utuh', function () {
    $grn = $this->g->dokumen(DocumentType::GRN, [['item' => $this->barang, 'planned' => 10]]);

    scan(['type' => 'GRN', 'document_no' => $grn->number, 'barcode' => 'DUS-SKU-00001'])
        ->assertUnprocessable()
        ->assertJson(['kode' => 'MELEBIHI_PESANAN']);

    expect($grn->lines()->sole()->qty_scanned)->toBe(0);
});

it('putaway: scan lokasi rak lalu barang, stok pindah saat selesai (UAT-06)', function () {
    $this->g->stokAwal($this->barang, $this->g->rcv, 5);
    $put = $this->g->dokumen(DocumentType::PUT, [['item' => $this->barang, 'from' => $this->g->rcv, 'planned' => 5]]);

    scan(['type' => 'PUT', 'document_no' => $put->number, 'barcode' => 'LOC-A-01-01'])
        ->assertOk()->assertJson(['jenis' => 'lokasi', 'lokasi' => ['kode' => 'A-01-01']]);
    scan(['type' => 'PUT', 'document_no' => $put->number, 'barcode' => $this->ean->barcode, 'location' => 'A-01-01', 'qty' => 5])
        ->assertOk();
    $this->postJson("/api/scan/documents/{$put->number}/complete")->assertOk();

    expect(Gudang::saldo($this->barang, $this->g->binA))->toBe(5)
        ->and(Gudang::saldo($this->barang, $this->g->rcv))->toBe(0);
    Gudang::assertLedgerCocok();
});

it('transfer antar lokasi (UAT-13)', function () {
    $this->g->stokAwal($this->barang, $this->g->binA, 10);
    $trf = $this->g->dokumen(DocumentType::TRF);

    scan(['type' => 'TRF', 'document_no' => $trf->number, 'barcode' => $this->ean->barcode, 'location' => 'A-01-01', 'to_location' => 'LOC-B-01-01', 'qty' => 4])
        ->assertOk();
    $this->postJson("/api/scan/documents/{$trf->number}/complete")->assertOk();

    expect(Gudang::saldo($this->barang, $this->g->binA))->toBe(6)
        ->and(Gudang::saldo($this->barang, $this->g->binB))->toBe(4);
});

it('transfer ke lokasi yang sama ditolak', function () {
    $this->g->stokAwal($this->barang, $this->g->binA, 10);
    $trf = $this->g->dokumen(DocumentType::TRF);

    scan(['type' => 'TRF', 'document_no' => $trf->number, 'barcode' => $this->ean->barcode, 'location' => 'A-01-01', 'to_location' => 'A-01-01'])
        ->assertUnprocessable()->assertJson(['kode' => 'LOKASI_SALAH']);
});

it('lokasi dikenali lewat kode atau barcode, dengan spasi sisa scanner', function (string $lokasi) {
    $this->g->stokAwal($this->barang, $this->g->binA, 10);
    $trf = $this->g->dokumen(DocumentType::TRF);

    scan(['type' => 'TRF', 'document_no' => $trf->number, 'barcode' => " {$this->ean->barcode}\r\n", 'location' => $lokasi, 'to_location' => 'B-01-01'])
        ->assertOk();
})->with(['A-01-01', 'LOC-A-01-01', "  LOC-A-01-01\r\n"]);

it('tidak menggandakan scan dengan client_scan_id sama (UAT-31)', function () {
    $grn = $this->g->dokumen(DocumentType::GRN);
    $body = ['type' => 'GRN', 'document_no' => $grn->number, 'barcode' => $this->ean->barcode, 'client_scan_id' => '4f0c8f7e-2b1a-4c3d-9e8f-0a1b2c3d4e5f'];

    $pertama = scan($body)->assertOk()->json();
    $kedua = scan($body)->assertOk()->json();

    expect($kedua)->toEqual($pertama)
        ->and($grn->lines()->sole()->qty_scanned)->toBe(1)
        ->and(ScanLog::count())->toBe(1);
});

it('respons scan OPN tidak memuat qty sistem (UAT-14)', function () {
    $this->g->stokAwal($this->barang, $this->g->binA, 37);
    $opn = $this->g->dokumen(DocumentType::OPN);
    app(OpnameService::class)->setLocations($opn, [$this->g->binA->id]);

    $respons = scan(['type' => 'OPN', 'document_no' => $opn->number, 'barcode' => $this->ean->barcode, 'location' => 'A-01-01'])
        ->assertOk()->assertJson(['baris' => ['qty_scan' => 1]]);

    expect($respons->getContent())->not->toContain('system')->not->toContain('sistem')->not->toContain('37');
});

it('opname: lokasi di luar dokumen ditolak', function () {
    $opn = $this->g->dokumen(DocumentType::OPN);
    app(OpnameService::class)->setLocations($opn, [$this->g->binA->id]);

    scan(['type' => 'OPN', 'document_no' => $opn->number, 'barcode' => $this->ean->barcode, 'location' => 'B-01-01'])
        ->assertUnprocessable()->assertJson(['kode' => 'LOKASI_SALAH']);
});

it('dokumen tak dikenal 404 dan tipe salah 422', function () {
    $grn = $this->g->dokumen(DocumentType::GRN);

    scan(['type' => 'GRN', 'document_no' => 'GRN-0000-99999', 'barcode' => $this->ean->barcode])
        ->assertNotFound()->assertJson(['kode' => 'DOKUMEN_TIDAK_DIKENAL']);
    scan(['type' => 'OUT', 'document_no' => $grn->number, 'barcode' => $this->ean->barcode, 'location' => 'A-01-01'])
        ->assertUnprocessable()->assertJson(['kode' => 'DOKUMEN_TIDAK_AKTIF']);
});

it('dokumen selesai tidak bisa di-scan', function () {
    $grn = $this->g->dokumen(DocumentType::GRN, [], ['status' => DocumentStatus::Selesai]);

    scan(['type' => 'GRN', 'document_no' => $grn->number, 'barcode' => $this->ean->barcode])
        ->assertUnprocessable()->assertJson(['kode' => 'DOKUMEN_TIDAK_AKTIF']);
});
