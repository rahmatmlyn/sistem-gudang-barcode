<?php

namespace App\Imports;

use App\Enums\BarcodeType;
use App\Models\Item;
use App\Models\ItemBarcode;
use App\Rules\Ean13Valid;
use App\Support\BarcodeRegistry;
use Closure;

/** Kolom sama dengan database/dummy/item_barcodes.csv; upsert per barcode, SKU harus sudah ada. */
class ItemBarcodeRowImporter extends RowImporter
{
    public function columns(): array
    {
        return ['barcode', 'sku', 'type', 'unit', 'factor'];
    }

    protected function rowKey(array $row): string
    {
        return $row['barcode'];
    }

    protected function rules(array $row): array
    {
        return [
            'barcode' => ['required', 'string', 'max:100', new Ean13Valid(BarcodeType::fromLabel($row['type'] ?? null))],
            'sku' => ['required', 'string'],
            'type' => ['required', function (string $attribute, mixed $value, Closure $fail) {
                if (BarcodeType::fromLabel($value) === null) {
                    $fail("Jenis barcode \"{$value}\" tidak dikenal (EAN-13, Code 128, atau QR).");
                }
            }],
            'unit' => ['required', 'string', 'max:30'],
            'factor' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function persist(array $row): void
    {
        $item = Item::query()->where('sku', $row['sku'])->first()
            ?? throw new ImportRowException("SKU {$row['sku']} tidak ditemukan; import barang lebih dulu.");

        $barcode = ItemBarcode::query()->firstOrNew(['barcode' => $row['barcode']]);
        if ($barcode->exists && $barcode->item_id !== $item->id || ! $barcode->exists && BarcodeRegistry::isTaken($row['barcode'])) {
            throw new ImportRowException("Barcode {$row['barcode']} sudah dipakai barang atau lokasi lain.");
        }

        $barcode->fill([
            'item_id' => $item->id,
            'type' => BarcodeType::fromLabel($row['type']),
            'unit' => $row['unit'],
            'factor' => (int) $row['factor'],
        ])->save();
    }
}
