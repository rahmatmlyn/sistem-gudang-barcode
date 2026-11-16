<?php

namespace App\Http\Presenters;

use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\OpnameLocation;

/** Bentuk JSON dokumen untuk halaman /scan. Tidak pernah memuat qty_system (hitung buta, UAT-14). */
final class ScanDocumentPresenter
{
    public static function summary(Document $document): array
    {
        $document->loadMissing('lines');

        return [
            'nomor' => $document->number,
            'tipe' => $document->type->value,
            'status' => $document->status->value,
            'mitra' => $document->partner,
            'referensi' => $document->reference,
            'dibuat' => $document->created_at?->toIso8601String(),
            'progres' => [
                'scan' => (int) $document->lines->sum('qty_scanned'),
                'rencana' => (int) $document->lines->sum('qty_planned'),
            ],
        ];
    }

    public static function detail(Document $document): array
    {
        $lines = $document->type === DocumentType::OUT
            ? $document->pickingLines()->with(['item', 'fromLocation', 'toLocation'])->get()
            : $document->lines()->with(['item', 'fromLocation', 'toLocation'])->orderBy('id')->get();

        $detail = [...self::summary($document), 'baris' => $lines->map(fn (DocumentLine $line) => [
            'id' => $line->id,
            'sku' => $line->item->sku,
            'nama' => $line->item->name,
            'satuan' => $line->item->base_unit,
            'lokasi_asal' => $line->fromLocation?->code,
            'lokasi_tujuan' => $line->toLocation?->code,
            'qty_rencana' => $line->qty_planned,
            'qty_scan' => $line->qty_scanned,
            'qty_pack' => $line->qty_packed,
        ])->values()->all()];

        if ($document->type === DocumentType::OPN) {
            $detail['diajukan'] = $document->submitted_at !== null;
            $detail['lokasi'] = $document->opnameLocations()->with('location')->get()
                ->sortBy(fn (OpnameLocation $row) => $row->location->code)
                ->map(fn (OpnameLocation $row) => ['kode' => $row->location->code, 'status' => $row->status->value])
                ->values()->all();
        }

        return $detail;
    }
}
