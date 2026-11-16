<?php

namespace App\Services\Scan;

use App\Enums\LocationType;
use App\Enums\ScanMode;
use App\Exceptions\MelebihiRencanaException;
use App\Exceptions\ScanDitolakException;
use App\Models\DocumentLine;
use App\Models\ItemBarcode;
use App\Models\Location;
use App\Services\StockService;
use Illuminate\Support\Collection;

/**
 * Putaway (PUT: location = rak/bin tujuan, asal = area terima dari rencana) dan transfer
 * (TRF: location = asal, to_location = tujuan). Scan mencadangkan stok asal lewat StockService::reserve.
 */
class MoveScanHandler implements ScanHandler
{
    use MarksDocumentInProcess;

    public function __construct(private StockService $stock) {}

    public function onLocation(ScanInput $input, Location $location): ScanResult
    {
        return ScanResult::lokasi($location, "Lokasi {$location->code}");
    }

    public function onItem(ScanInput $input, ItemBarcode $barcode, int $qtyBase): ScanResult
    {
        $lines = $input->document->lines()->where('item_id', $barcode->item_id)->orderBy('id')->lockForUpdate()->get();
        [$fromId, $to] = $input->mode === ScanMode::PUT
            ? $this->putawayRoute($input, $barcode, $lines, $qtyBase)
            : $this->transferRoute($input, $barcode, $lines);

        if ($fromId === $to->id) {
            throw ScanDitolakException::lokasiSalah("Lokasi asal dan tujuan sama ({$to->code}).");
        }

        $line = $lines->first(fn (DocumentLine $line) => $line->from_location_id === $fromId && $line->to_location_id === $to->id)
            ?? $this->claimPlanLine($lines, $fromId, $to)
            ?? DocumentLine::create([
                'document_id' => $input->document->id, 'item_id' => $barcode->item_id,
                'from_location_id' => $fromId, 'to_location_id' => $to->id,
            ]);

        $this->stock->reserve($line, $qtyBase);
        $this->markInProcess($input->document);

        $aksi = $input->mode === ScanMode::PUT ? "Ke {$to->code}:" : "Transfer ke {$to->code}:";

        return ScanResult::barang($barcode, $qtyBase, ScanResult::baris($line), ScanResult::pesanBarang($barcode, $qtyBase, $aksi));
    }

    /** @return array{0: int, 1: Location} */
    private function putawayRoute(ScanInput $input, ItemBarcode $barcode, Collection $lines, int $qtyBase): array
    {
        $to = $input->location ?? throw ScanDitolakException::lokasiSalah('Scan lokasi rak/bin tujuan dulu.');
        if (in_array($to->type, [LocationType::AreaTerima, LocationType::AreaKirim], true)) {
            throw ScanDitolakException::lokasiSalah("Tujuan putaway harus rak atau bin, bukan {$to->code}.");
        }
        $plan = $lines->first() ?? throw ScanDitolakException::barangSalah("{$barcode->item->name} tidak ada di dokumen putaway {$input->document->number}.");

        $planned = (int) $lines->sum('qty_planned');
        $scanned = (int) $lines->sum('qty_scanned');
        if ($planned > 0 && $scanned + $qtyBase > $planned) {
            throw new MelebihiRencanaException("Putaway {$barcode->item->name} melebihi rencana: rencana {$planned}, sudah {$scanned}, scan +{$qtyBase}.");
        }

        return [$plan->from_location_id, $to];
    }

    /** @return array{0: int, 1: Location} */
    private function transferRoute(ScanInput $input, ItemBarcode $barcode, Collection $lines): array
    {
        $from = $input->location ?? throw ScanDitolakException::lokasiSalah('Scan lokasi asal dulu.');
        $to = $input->toLocation ?? throw ScanDitolakException::lokasiSalah('Scan lokasi tujuan dulu.');

        $planned = $lines->filter(fn (DocumentLine $line) => $line->qty_planned > 0);
        if ($planned->isNotEmpty() && ! $planned->contains(fn (DocumentLine $line) => $line->from_location_id === $from->id && $line->to_location_id === $to->id)) {
            $rute = $planned->map(fn (DocumentLine $line) => "{$line->fromLocation->code} → {$line->toLocation->code}")->implode(', ');
            throw ScanDitolakException::lokasiSalah("Transfer {$barcode->item->name} direncanakan {$rute}.");
        }

        return [$from->id, $to];
    }

    /** Baris rencana putaway (tujuan belum ditentukan, belum di-scan) dipakai untuk tujuan pertama. */
    private function claimPlanLine(Collection $lines, int $fromId, Location $to): ?DocumentLine
    {
        $plan = $lines->first(fn (DocumentLine $line) => $line->from_location_id === $fromId && $line->to_location_id === null && $line->qty_scanned === 0);
        $plan?->update(['to_location_id' => $to->id]);

        return $plan;
    }
}
