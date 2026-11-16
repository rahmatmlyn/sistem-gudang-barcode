<?php

namespace App\Services\Scan;

use App\Enums\DocumentStatus;
use App\Models\Document;

trait MarksDocumentInProcess
{
    /** Scan pertama memindahkan dokumen draft ke proses (dokumen sudah dikunci pemanggil). */
    private function markInProcess(Document $document): void
    {
        if ($document->status === DocumentStatus::Draft) {
            $document->update(['status' => DocumentStatus::Proses]);
        }
    }
}
