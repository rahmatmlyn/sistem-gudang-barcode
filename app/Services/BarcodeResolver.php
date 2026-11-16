<?php

namespace App\Services;

use App\Exceptions\BarcodeTidakDikenalException;
use App\Exceptions\ScanDitolakException;
use App\Models\ItemBarcode;
use App\Models\Location;

/**
 * Menerjemahkan teks dari scanner (keyboard wedge) menjadi barcode barang atau lokasi.
 * Sisa spasi/CR/LF dari scanner dibuang. Lokasi dikenali lewat barcode (LOC-A-01-02) maupun kode (A-01-02).
 */
class BarcodeResolver
{
    public static function clean(?string $raw): string
    {
        return trim((string) $raw, " \t\n\r\0\x0B");
    }

    public function resolve(string $raw, ?int $warehouseId = null): ItemBarcode|Location
    {
        $code = self::clean($raw);

        return ItemBarcode::query()->with('item')->where('barcode', $code)->first()
            ?? $this->findLocation($code, $warehouseId)
            ?? throw new BarcodeTidakDikenalException("Barcode {$code} tidak dikenal.");
    }

    public function item(string $raw): ItemBarcode
    {
        $resolved = $this->resolve($raw);

        return $resolved instanceof ItemBarcode
            ? $resolved
            : throw ScanDitolakException::barangSalah("{$resolved->code} adalah barcode lokasi. Scan barcode barang.");
    }

    public function location(string $raw, ?int $warehouseId = null): Location
    {
        $code = self::clean($raw);

        return $this->findLocation($code, $warehouseId)
            ?? throw new BarcodeTidakDikenalException("Lokasi {$code} tidak dikenal.");
    }

    private function findLocation(string $code, ?int $warehouseId): ?Location
    {
        if ($code === '') {
            return null;
        }

        $query = fn () => Location::query()->when($warehouseId, fn ($query) => $query->where('warehouse_id', $warehouseId));

        return $query()->where('barcode', $code)->first() ?? $query()->where('code', $code)->first();
    }
}
