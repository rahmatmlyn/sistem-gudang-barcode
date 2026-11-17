<?php

use App\Imports\ItemBarcodeRowImporter;
use App\Imports\ItemRowImporter;
use App\Imports\LocationRowImporter;
use App\Models\Item;
use App\Models\ItemBarcode;
use App\Models\Location;
use App\Models\Warehouse;
use App\Support\Ean13;
use Tests\Support\Lembar;

const KOLOM_BARANG = ['sku', 'name', 'category', 'base_unit', 'min_stock'];
const KOLOM_BARCODE = ['barcode', 'sku', 'type', 'unit', 'factor'];
const KOLOM_LOKASI = ['code', 'warehouse', 'type', 'parent', 'barcode'];

it('import 1000 baris dengan 5 salah: 995 masuk, 5 dilaporkan dengan nomor baris (UAT-18)', function () {
    $rows = [KOLOM_BARANG];
    foreach (range(1, 1000) as $urutan) {
        $rows[] = [sprintf('SKU-%05d', $urutan), "Barang {$urutan}", 'Umum', 'pcs', 10];
    }
    // indeks larik = nomor baris di lembar - 1 (baris 1 = header)
    $rows[10][1] = '';
    $rows[200][3] = '';
    $rows[450][4] = 'sepuluh';
    $rows[700][4] = -5;
    $rows[999][0] = '';

    $laporan = app(ItemRowImporter::class)->import(Lembar::xlsx($rows));

    expect($laporan->berhasil)->toBe(995)
        ->and(array_keys($laporan->gagal))->toBe([11, 201, 451, 701, 1000])
        ->and($laporan->gagal[11][0])->toContain('Nama')
        ->and($laporan->gagal[451][0])->toContain('Stok minimum')
        ->and(Item::count())->toBe(995);
});

it('menerima csv dan xlsx', function (Closure $tulis) {
    $laporan = app(ItemRowImporter::class)->import($tulis([
        KOLOM_BARANG,
        ['SKU-00001', 'Gula Pasir', 'Sembako', 'kg', 5],
        ['SKU-00002', 'Teh Celup', null, 'pcs', null],
    ]));

    expect($laporan->berhasil)->toBe(2)->and($laporan->gagal)->toBe([]);
    expect(Item::where('sku', 'SKU-00002')->sole())
        ->category->toBeNull()
        ->min_stock->toBe(0);
})->with([
    'csv' => [fn (array $rows) => Lembar::csv($rows)],
    'csv titik koma + BOM' => [fn (array $rows) => Lembar::csv($rows, ';', bom: true)],
    'xlsx' => [fn (array $rows) => Lembar::xlsx($rows)],
]);

it('baris dengan sku yang sudah ada memperbarui barang', function () {
    Item::factory()->create(['sku' => 'SKU-00001', 'name' => 'Nama Lama', 'min_stock' => 1]);

    app(ItemRowImporter::class)->import(Lembar::csv([KOLOM_BARANG, ['SKU-00001', 'Nama Baru', 'Umum', 'pcs', 7]]));

    expect(Item::sole())->name->toBe('Nama Baru')->min_stock->toBe(7);
});

it('sku kembar dalam satu file dilaporkan', function () {
    $laporan = app(ItemRowImporter::class)->import(Lembar::csv([
        KOLOM_BARANG, ['SKU-00001', 'A', null, 'pcs', 0], ['SKU-00001', 'B', null, 'pcs', 0],
    ]));

    expect($laporan->berhasil)->toBe(1)
        ->and($laporan->gagal[3][0])->toContain('baris 2');
    expect(Item::sole()->name)->toBe('A');
});

it('file tanpa kolom wajib ditolak utuh', function () {
    $laporan = app(ItemRowImporter::class)->import(Lembar::csv([['sku', 'nama'], ['SKU-00001', 'A']]));

    expect($laporan->berhasil)->toBe(0)
        ->and($laporan->gagal[1][0])->toContain('base_unit');
    expect(Item::count())->toBe(0);
});

it('import barcode: sku tak dikenal, barcode milik barang lain, dan EAN-13 salah dilaporkan', function () {
    $barang = Item::factory()->create(['sku' => 'SKU-00001']);
    ItemBarcode::factory()->create(['barcode' => 'DUS-LAIN']);
    $ean = Ean13::complete('899100000001');

    $laporan = app(ItemBarcodeRowImporter::class)->import(Lembar::xlsx([
        KOLOM_BARCODE,
        [(int) $ean, 'SKU-00001', 'EAN-13', 'satuan dasar', 1],
        ['DUS-SKU-00001', 'SKU-00001', 'Code 128', 'dus', 24],
        ['DUS-X', 'SKU-99999', 'Code 128', 'dus', 12],
        ['DUS-LAIN', 'SKU-00001', 'Code 128', 'dus', 12],
        ['8991000000010', 'SKU-00001', 'EAN-13', 'pcs', 1],
        ['DUS-NOL', 'SKU-00001', 'Code 128', 'dus', 0],
    ]));

    expect($laporan->berhasil)->toBe(2)
        ->and(array_keys($laporan->gagal))->toBe([4, 5, 6, 7])
        ->and($laporan->gagal[4][0])->toContain('SKU-99999')
        ->and($laporan->gagal[5][0])->toContain('sudah dipakai');
    expect($barang->barcodes()->pluck('factor', 'barcode')->all())
        ->toEqualCanonicalizing([$ean => 1, 'DUS-SKU-00001' => 24]);
});

it('lokasi dengan parent yang belum ada di baris lebih awal tetap terimport', function () {
    Warehouse::factory()->create(['code' => 'GU']);

    $laporan = app(LocationRowImporter::class)->import(Lembar::csv([
        KOLOM_LOKASI,
        ['A-01-01', 'GU', 'bin', 'A-01', 'LOC-A-01-01'],
        ['A-01', 'GU', 'rak', 'A', 'LOC-A-01'],
        ['A', 'GU', 'zona', 'GU', null],
        ['X-01', 'GU', 'rak', 'X', 'LOC-X-01'],
        ['RCV-01', 'GU', 'area terima', 'GU', 'LOC-RCV-01'],
    ]));

    expect($laporan->berhasil)->toBe(4)
        ->and(array_keys($laporan->gagal))->toBe([5])
        ->and($laporan->gagal[5][0])->toContain('X');
    expect(Location::with('parent.parent')->where('code', 'A-01-01')->sole()->path)->toBe('A › A-01 › A-01-01');
    expect(Location::where('code', 'A')->sole())->barcode->toBe('LOC-A')->parent_id->toBeNull();
});

it('import lokasi menolak gudang tak dikenal, tipe salah, dan siklus induk', function () {
    $gudang = Warehouse::factory()->create(['code' => 'GU']);
    $zona = Location::factory()->create(['warehouse_id' => $gudang->id, 'code' => 'A', 'type' => 'zona', 'barcode' => 'LOC-A']);
    Location::factory()->create(['warehouse_id' => $gudang->id, 'code' => 'A-01', 'type' => 'rak', 'parent_id' => $zona->id, 'barcode' => 'LOC-A-01']);

    $laporan = app(LocationRowImporter::class)->import(Lembar::csv([
        KOLOM_LOKASI,
        ['B', 'GX', 'zona', 'GX', null],
        ['C', 'GU', 'lorong', 'GU', null],
        ['A', 'GU', 'zona', 'A-01', 'LOC-A'],
    ]));

    expect($laporan->berhasil)->toBe(0)
        ->and(array_keys($laporan->gagal))->toBe([2, 3, 4])
        ->and($laporan->gagal[2][0])->toContain('GX')
        ->and($laporan->gagal[4][0])->toContain('turunan');
    expect($zona->fresh()->parent_id)->toBeNull();
});

it('mengimport data dummy proyek tanpa baris gagal', function () {
    Warehouse::factory()->create(['code' => 'GU']);

    $barang = app(ItemRowImporter::class)->import(base_path('database/dummy/items.csv'));
    $barcode = app(ItemBarcodeRowImporter::class)->import(base_path('database/dummy/item_barcodes.csv'));
    $lokasi = app(LocationRowImporter::class)->import(base_path('database/dummy/locations.csv'));

    expect([$barang->gagal, $barcode->gagal, $lokasi->gagal])->toBe([[], [], []])
        ->and([$barang->berhasil, $barcode->berhasil, $lokasi->berhasil])->toBe([1000, 1617, 246]);
});

it('laporan gagal bisa diekspor sebagai csv baris,pesan', function () {
    $laporan = app(ItemRowImporter::class)->import(Lembar::csv([KOLOM_BARANG, ['', 'A', null, 'pcs', 0]]));

    expect($laporan->toCsv())->toBe("baris,pesan\n2,\"SKU wajib diisi.\"\n");
});

it('kunci kembar dalam file dibandingkan peka huruf, sama seperti database', function () {
    $laporan = app(ItemRowImporter::class)->import(Lembar::csv([
        KOLOM_BARANG, ['SKU-AB', 'Besar', null, 'pcs', 0], ['sku-ab', 'Kecil', null, 'pcs', 0],
    ]));

    expect($laporan->gagal)->toBe([])->and(Item::count())->toBe(2);
});
