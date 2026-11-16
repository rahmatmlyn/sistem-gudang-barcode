<?php

namespace App\Services\Scan;

use App\Enums\DocumentStatus;
use App\Exceptions\DokumenTidakAktifException;
use App\Exceptions\ScanDitolakException;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\ItemBarcode;
use App\Models\Location;
use App\Services\StockService;

/** Picking (OUT, dua langkah): scan lokasi lalu barang; barang/lokasi harus sesuai daftar picking (UAT-08, UAT-09). */
class PickScanHandler implements ScanHandler
{
    public function __construct(private StockService $stock) {}

    public function onLocation(ScanInput $input, Location $location): ScanResult
    {
        self::requireReleased($input->document);
        if (! $input->document->lines()->where('from_location_id', $location->id)->exists()) {
            throw ScanDitolakException::lokasiSalah("Lokasi {$location->code} tidak ada di daftar picking {$input->document->number}.");
        }

        return ScanResult::lokasi($location, "Lokasi {$location->code} benar. Scan barang.");
    }

    public function onItem(ScanInput $input, ItemBarcode $barcode, int $qtyBase): ScanResult
    {
        self::requireReleased($input->document);
        $location = $input->location ?? throw ScanDitolakException::lokasiSalah('Scan lokasi rak dulu.');
        $lines = $input->document->lines()->with(['item', 'fromLocation'])->orderBy('id')->get();
        $forItem = $lines->where('item_id', $barcode->item_id);

        if ($forItem->isEmpty()) {
            $expected = $lines->where('from_location_id', $location->id)->map(fn (DocumentLine $line) => $line->item->name)->implode(', ');
            throw ScanDitolakException::barangSalah("Yang di-scan: {$barcode->item->name}, tidak ada di pesanan."
                .($expected !== '' ? " Ambil {$expected}." : ''));
        }

        $line = $forItem->firstWhere('from_location_id', $location->id)
            ?? throw ScanDitolakException::lokasiSalah("Ambil {$barcode->item->name} dari "
                .$forItem->map(fn (DocumentLine $line) => $line->fromLocation->code)->implode(', ').', bukan dari '.$location->code.'.');

        $this->stock->reserve($line, $qtyBase);

        return ScanResult::barang($barcode, $qtyBase, ScanResult::baris($line), ScanResult::pesanBarang($barcode, $qtyBase, 'Diambil'));
    }

    public static function requireReleased(Document $document): void
    {
        if ($document->status !== DocumentStatus::Proses) {
            throw new DokumenTidakAktifException("Pesanan {$document->number} belum dirilis ke picking.");
        }
    }
}
