<?php

namespace App\Services\Scan;

use App\Exceptions\MelebihiRencanaException;
use App\Exceptions\ScanDitolakException;
use App\Models\DocumentLine;
use App\Models\ItemBarcode;
use App\Models\Location;

/** Penerimaan (GRN): qty_scanned bertambah; saldo baru berubah saat GRN diselesaikan (aturan 1). */
class ReceiveScanHandler implements ScanHandler
{
    use MarksDocumentInProcess;

    public function onLocation(ScanInput $input, Location $location): ScanResult
    {
        throw ScanDitolakException::barangSalah("{$location->code} adalah barcode lokasi. Pada penerimaan, scan barcode barang.");
    }

    public function onItem(ScanInput $input, ItemBarcode $barcode, int $qtyBase): ScanResult
    {
        $document = $input->document;
        $line = $document->lines()->where('item_id', $barcode->item_id)->orderBy('id')->lockForUpdate()->first()
            ?? DocumentLine::create(['document_id' => $document->id, 'item_id' => $barcode->item_id]);

        if ($line->qty_planned > 0 && $line->qty_scanned + $qtyBase > $line->qty_planned) {
            throw new MelebihiRencanaException(
                "Penerimaan {$barcode->item->name} melebihi rencana: rencana {$line->qty_planned}, sudah {$line->qty_scanned}, scan +{$qtyBase}."
            );
        }

        $line->increment('qty_scanned', $qtyBase);
        $this->markInProcess($document);

        return ScanResult::barang($barcode, $qtyBase, ScanResult::baris($line), ScanResult::pesanBarang($barcode, $qtyBase, 'Diterima'));
    }
}
