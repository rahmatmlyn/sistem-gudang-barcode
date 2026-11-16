<?php

namespace App\Services\Scan;

use App\Exceptions\QtyTidakCocokException;
use App\Exceptions\ScanDitolakException;
use App\Models\ItemBarcode;
use App\Models\Location;

/** Verifikasi packing (PACK pada dokumen OUT): qty_packed per barang tidak boleh melebihi qty picking. */
class PackScanHandler implements ScanHandler
{
    public function onLocation(ScanInput $input, Location $location): ScanResult
    {
        throw ScanDitolakException::barangSalah("{$location->code} adalah barcode lokasi. Pada packing, scan barcode barang.");
    }

    public function onItem(ScanInput $input, ItemBarcode $barcode, int $qtyBase): ScanResult
    {
        PickScanHandler::requireReleased($input->document);
        $lines = $input->document->lines()->where('item_id', $barcode->item_id)->orderBy('id')->lockForUpdate()->get();
        if ($lines->isEmpty()) {
            throw ScanDitolakException::barangSalah("{$barcode->item->name} tidak ada di pesanan {$input->document->number}.");
        }

        $picked = (int) $lines->sum('qty_scanned');
        $packed = (int) $lines->sum('qty_packed');
        if ($packed + $qtyBase > $picked) {
            throw new QtyTidakCocokException("Packing {$barcode->item->name} melebihi qty picking: diambil {$picked}, sudah dipacking {$packed}.");
        }

        $remaining = $qtyBase;
        foreach ($lines as $line) {
            $take = min($remaining, $line->qty_scanned - $line->qty_packed);
            if ($take > 0) {
                $line->increment('qty_packed', $take);
                $remaining -= $take;
            }
        }

        return ScanResult::barang($barcode, $qtyBase, [
            'id' => $lines->first()->id,
            'qty_scan' => $picked,
            'qty_rencana' => (int) $lines->sum('qty_planned'),
            'qty_pack' => $packed + $qtyBase,
        ], ScanResult::pesanBarang($barcode, $qtyBase, 'Dipacking'));
    }
}
