<?php

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\ItemBarcode;
use App\Services\PickingAllocator;
use App\Services\StockService;
use App\Support\Ean13;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Gudang;

beforeEach(function () {
    $this->g = Gudang::siapkan();
    $this->barang = $this->g->barang(['sku' => 'SKU-00001', 'name' => 'Minyak Goreng 2 L']);
    $this->ean = Ean13::complete('899100000001');
    ItemBarcode::factory()->create(['item_id' => $this->barang->id, 'barcode' => $this->ean, 'type' => 'EAN-13']);
    ItemBarcode::factory()->dus(24)->create(['item_id' => $this->barang->id, 'barcode' => 'DUS-SKU-00001']);
    Sanctum::actingAs($this->g->staf);
});

/** Pesanan keluar yang sudah dirilis: barang diambil dari binA. */
function pesananDirilis(Gudang $g, $barang, int $stok, int $pesan)
{
    $g->stokAwal($barang, $g->binA, $stok);
    $out = $g->dokumen(DocumentType::OUT, [['item' => $barang, 'planned' => $pesan]]);
    app(PickingAllocator::class)->allocate($out, $g->supervisor);

    return $out->fresh('lines');
}

function ambil($out, string $barcode, int $qty = 1, string $lokasi = 'A-01-01', string $mode = 'OUT')
{
    return test()->postJson('/api/scan', ['type' => $mode, 'document_no' => $out->number, 'barcode' => $barcode, 'location' => $lokasi, 'qty' => $qty]);
}

it('picking: barang salah 422 BARANG_SALAH, qty tetap (UAT-08)', function () {
    $out = pesananDirilis($this->g, $this->barang, 50, 10);
    $lain = ItemBarcode::factory()->create(['barcode' => 'BARANG-LAIN']);

    ambil($out, 'BARANG-LAIN')->assertUnprocessable()->assertJson(['kode' => 'BARANG_SALAH']);

    expect($out->lines()->sole()->qty_scanned)->toBe(0);
});

it('picking: lokasi salah 422 LOKASI_SALAH (UAT-09)', function () {
    $out = pesananDirilis($this->g, $this->barang, 50, 10);

    ambil($out, $this->ean, lokasi: 'B-01-01')
        ->assertUnprocessable()
        ->assertJson(['kode' => 'LOKASI_SALAH'])
        ->assertJsonPath('pesan', fn (string $pesan) => str_contains($pesan, 'A-01-01'));
    $this->postJson('/api/scan', ['type' => 'OUT', 'document_no' => $out->number, 'barcode' => 'LOC-B-01-01'])
        ->assertUnprocessable()->assertJson(['kode' => 'LOKASI_SALAH']);
    $this->postJson('/api/scan', ['type' => 'OUT', 'document_no' => $out->number, 'barcode' => 'LOC-A-01-01'])
        ->assertOk()->assertJson(['jenis' => 'lokasi']);
});

it('picking melebihi stok 409 STOK_KURANG (UAT-12)', function () {
    $out = pesananDirilis($this->g, $this->barang, 5, 5);
    $trf = $this->g->dokumen(DocumentType::TRF, [['item' => $this->barang, 'from' => $this->g->binA, 'to' => $this->g->binB]]);
    app(StockService::class)->reserve($trf->lines->first(), 3);

    ambil($out, $this->ean, 3)->assertStatus(409)->assertJson(['kode' => 'STOK_KURANG']);

    expect($out->lines()->sole()->qty_scanned)->toBe(0);
});

it('scan dus melebihi sisa pesanan ditolak utuh', function () {
    $out = pesananDirilis($this->g, $this->barang, 50, 10);

    ambil($out, 'DUS-SKU-00001')->assertUnprocessable()->assertJson(['kode' => 'MELEBIHI_PESANAN']);

    expect($out->lines()->sole()->qty_scanned)->toBe(0);
});

it('pesanan yang belum dirilis tidak bisa di-picking', function () {
    $out = $this->g->dokumen(DocumentType::OUT, [['item' => $this->barang, 'planned' => 2]]);

    ambil($out, $this->ean)->assertUnprocessable()->assertJson(['kode' => 'DOKUMEN_TIDAK_AKTIF']);
});

it('packing melebihi qty picking ditolak', function () {
    $out = pesananDirilis($this->g, $this->barang, 50, 10);
    ambil($out, $this->ean, 3)->assertOk();

    ambil($out, $this->ean, 3, mode: 'PACK')->assertOk()->assertJson(['baris' => ['qty_pack' => 3]]);
    ambil($out, $this->ean, 1, mode: 'PACK')->assertUnprocessable()->assertJson(['kode' => 'QTY_TIDAK_COCOK']);
});

it('kirim ditolak bila qty packing tidak cocok (UAT-10)', function () {
    $out = pesananDirilis($this->g, $this->barang, 50, 10);
    ambil($out, $this->ean, 10)->assertOk();
    ambil($out, $this->ean, 8, mode: 'PACK')->assertOk();

    $this->postJson("/api/scan/documents/{$out->number}/complete")
        ->assertUnprocessable()->assertJson(['kode' => 'QTY_TIDAK_COCOK']);

    expect(Gudang::saldo($this->barang, $this->g->binA))->toBe(50)
        ->and($out->fresh()->status)->toBe(DocumentStatus::Proses);
});

it('kirim mengurangi stok dan surat jalan bisa diunduh (UAT-11)', function () {
    $out = pesananDirilis($this->g, $this->barang, 50, 10);
    ambil($out, $this->ean, 10)->assertOk();
    ambil($out, $this->ean, 10, mode: 'PACK')->assertOk();

    $this->postJson("/api/scan/documents/{$out->number}/complete")->assertOk()->assertJson(['status' => 'ok']);

    expect(Gudang::saldo($this->barang, $this->g->binA))->toBe(40)
        ->and($out->fresh()->status)->toBe(DocumentStatus::Selesai);
    $this->get("/api/scan/documents/{$out->number}/surat-jalan")
        ->assertOk()->assertHeader('content-type', 'application/pdf');
});

it('GET /api/stock mengembalikan qty tersedia', function () {
    $out = pesananDirilis($this->g, $this->barang, 50, 10);
    ambil($out, $this->ean, 4)->assertOk();
    Sanctum::actingAs($this->g->staf, ['uji-scan']);

    $this->getJson("/api/stock?barcode={$this->ean}&location=A-01-01")
        ->assertOk()->assertExactJson(['qty' => 46, 'saldo' => 50, 'dipesan' => 4]);
    $this->getJson('/api/stock?barcode=TIDAK-ADA&location=A-01-01')->assertNotFound();
});

it('laporkan kosong menyesuaikan rencana baris ke qty yang sudah terambil', function () {
    $out = pesananDirilis($this->g, $this->barang, 50, 10);
    ambil($out, $this->ean, 4)->assertOk();
    $baris = $out->lines()->sole();

    $this->patchJson("/api/scan/lines/{$baris->id}", ['kosong' => true])->assertOk()->assertJson(['baris' => ['qty_rencana' => 4]]);

    expect($baris->fresh()->qty_planned)->toBe(4);
});
