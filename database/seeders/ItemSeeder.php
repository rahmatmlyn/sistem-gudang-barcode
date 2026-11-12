<?php

namespace Database\Seeders;

use App\Enums\BarcodeType;
use App\Models\Item;
use App\Models\ItemBarcode;
use App\Support\Ean13;
use Illuminate\Database\Seeder;

class ItemSeeder extends Seeder
{
    /** @var list<array{0: string, 1: string, 2: string, 3: int, 4: int|null}> nama, kategori, satuan dasar, stok minimum, isi dus */
    private const BARANG = [
        ['Beras Premium 5 kg', 'Sembako', 'karung', 20, null],
        ['Minyak Goreng 1 L', 'Sembako', 'pcs', 48, 12],
        ['Gula Pasir 1 kg', 'Sembako', 'pcs', 40, 24],
        ['Tepung Terigu 1 kg', 'Sembako', 'pcs', 30, 24],
        ['Mi Instan Goreng', 'Sembako', 'pcs', 200, 40],
        ['Mi Instan Kuah Ayam', 'Sembako', 'pcs', 200, 40],
        ['Kecap Manis 520 ml', 'Bumbu', 'pcs', 24, 12],
        ['Saus Sambal 340 ml', 'Bumbu', 'pcs', 24, 24],
        ['Garam Beryodium 250 g', 'Bumbu', 'pcs', 30, 48],
        ['Teh Celup isi 25', 'Minuman', 'kotak', 20, 24],
        ['Kopi Bubuk 165 g', 'Minuman', 'pcs', 20, 24],
        ['Susu Kental Manis 370 g', 'Minuman', 'kaleng', 48, 48],
        ['Air Mineral 600 ml', 'Minuman', 'botol', 96, 24],
        ['Sabun Mandi Batang', 'Kebersihan', 'pcs', 60, 72],
        ['Sampo Sachet', 'Kebersihan', 'renceng', 30, null],
        ['Deterjen Bubuk 800 g', 'Kebersihan', 'pcs', 24, 12],
        ['Pasta Gigi 190 g', 'Kebersihan', 'pcs', 36, 48],
        ['Biskuit Kelapa 300 g', 'Snack', 'pcs', 24, 12],
        ['Keripik Kentang 68 g', 'Snack', 'pcs', 48, 6],
        ['Wafer Cokelat 130 g', 'Snack', 'pcs', 36, 24],
    ];

    public function run(): void
    {
        foreach (self::BARANG as $index => [$nama, $kategori, $satuan, $minimum, $isiDus]) {
            $nomor = $index + 1;
            $sku = sprintf('SKU-%05d', $nomor);

            $item = Item::updateOrCreate(
                ['sku' => $sku],
                ['name' => $nama, 'category' => $kategori, 'base_unit' => $satuan, 'min_stock' => $minimum],
            );

            ItemBarcode::updateOrCreate(
                ['barcode' => Ean13::complete(sprintf('8991000%05d', $nomor))],
                ['item_id' => $item->id, 'type' => BarcodeType::Ean13, 'unit' => $satuan, 'factor' => 1],
            );

            if ($isiDus !== null) {
                ItemBarcode::updateOrCreate(
                    ['barcode' => "DUS-{$sku}"],
                    ['item_id' => $item->id, 'type' => BarcodeType::Code128, 'unit' => 'dus', 'factor' => $isiDus],
                );
            }
        }
    }
}
