<?php

namespace App\Support;

use App\Models\ItemBarcode;
use App\Models\Location;
use Illuminate\Database\Eloquent\Model;

/**
 * Barcode barang dan barcode lokasi berbagi satu ruang nama: scanner tidak tahu jenisnya,
 * jadi satu kode tidak boleh dipakai di kedua tabel (penting untuk BarcodeResolver M6).
 */
final class BarcodeRegistry
{
    public static function isTaken(string $barcode, ?Model $ignore = null): bool
    {
        $barcode = trim($barcode);

        return self::existsIn(ItemBarcode::class, $barcode, $ignore)
            || self::existsIn(Location::class, $barcode, $ignore);
    }

    /** @param class-string<Model> $model */
    private static function existsIn(string $model, string $barcode, ?Model $ignore): bool
    {
        return $model::query()
            ->where('barcode', $barcode)
            ->when($ignore instanceof $model && $ignore->exists, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->exists();
    }
}
