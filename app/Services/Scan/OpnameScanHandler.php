<?php

namespace App\Services\Scan;

use App\Enums\OpnameLocationStatus;
use App\Exceptions\DokumenTidakAktifException;
use App\Exceptions\ScanDitolakException;
use App\Models\DocumentLine;
use App\Models\ItemBarcode;
use App\Models\Location;

/** Opname hitung buta: qty fisik per barang–lokasi. Respons tidak pernah memuat qty sistem (UAT-14). */
class OpnameScanHandler implements ScanHandler
{
    use MarksDocumentInProcess;

    public function onLocation(ScanInput $input, Location $location): ScanResult
    {
        $this->assertCountable($input, $location);

        return ScanResult::lokasi($location, "Lokasi {$location->code} aktif. Scan setiap barang di lokasi ini.");
    }

    public function onItem(ScanInput $input, ItemBarcode $barcode, int $qtyBase): ScanResult
    {
        $location = $input->location ?? throw ScanDitolakException::lokasiSalah('Scan lokasi dulu.');
        $this->assertCountable($input, $location);

        $line = $input->document->lines()->where('item_id', $barcode->item_id)->where('from_location_id', $location->id)
            ->lockForUpdate()->first()
            ?? DocumentLine::create(['document_id' => $input->document->id, 'item_id' => $barcode->item_id, 'from_location_id' => $location->id]);
        $line->increment('qty_scanned', $qtyBase);
        $this->markInProcess($input->document);

        return ScanResult::barang($barcode, $qtyBase, ScanResult::baris($line), ScanResult::pesanBarang($barcode, $qtyBase, 'Dihitung'));
    }

    private function assertCountable(ScanInput $input, Location $location): void
    {
        $document = $input->document;
        if ($document->submitted_at !== null) {
            throw new DokumenTidakAktifException("Opname {$document->number} sudah diajukan.");
        }

        $row = $document->opnameLocations()->where('location_id', $location->id)->first()
            ?? throw ScanDitolakException::lokasiSalah("Lokasi {$location->code} bukan bagian opname {$document->number}.");
        if ($row->status !== OpnameLocationStatus::Belum) {
            throw new DokumenTidakAktifException("Lokasi {$location->code} sudah selesai dihitung.");
        }
    }
}
