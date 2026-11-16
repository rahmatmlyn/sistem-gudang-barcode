<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Exceptions\DokumenTidakAktifException;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Siklus hidup dokumen di luar perubahan stok. Membatalkan dokumen terbuka melepas reservasinya (K1). */
class DocumentService
{
    public function __construct(private ActivityLogger $activity) {}

    public function cancel(Document $document, User $by, string $reason): void
    {
        $reason = trim($reason) !== '' ? trim($reason) : throw new InvalidArgumentException('Alasan wajib diisi.');

        DB::transaction(function () use ($document, $by, $reason) {
            $locked = Document::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
            if (! $locked->status->isOpen()) {
                throw new DokumenTidakAktifException("Dokumen {$locked->number} sudah {$locked->status->getLabel()}, tidak bisa dibatalkan.");
            }

            $before = $locked->status->value;
            $locked->update(['status' => DocumentStatus::Batal]);
            $this->activity->log($by, 'dokumen.dibatalkan', $locked, ['status' => $before], ['status' => 'batal', 'alasan' => $reason]);
        });

        $document->refresh();
    }
}
