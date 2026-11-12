<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\DocumentSequence;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Nomor dokumen otomatis per tipe dan tahun, format {TIPE}-{TAHUN}-{5 digit} (aturan 6).
 * Baris document_sequences dikunci agar dua permintaan bersamaan tidak mendapat nomor sama.
 */
class DocumentNumberService
{
    public function next(DocumentType $type, ?CarbonInterface $at = null): string
    {
        $year = ($at ?? now())->year;

        return DB::transaction(function () use ($type, $year) {
            DocumentSequence::query()->insertOrIgnore(['type' => $type->value, 'year' => $year, 'last_number' => 0]);

            $sequence = DocumentSequence::query()
                ->where('type', $type->value)
                ->where('year', $year)
                ->lockForUpdate()
                ->firstOrFail();

            $sequence->increment('last_number');

            return sprintf('%s-%d-%05d', $type->value, $year, $sequence->last_number);
        });
    }
}
