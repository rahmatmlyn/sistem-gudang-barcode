<?php

namespace App\Policies;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\User;

/**
 * Dokumen di panel (K6): Admin & Supervisor kelola, Staf hanya lewat /scan.
 * Aturan 3 / UAT-25: hanya draft yang bisa diedit/dihapus; dokumen selesai dikoreksi lewat transaksi balik.
 */
class DocumentPolicy
{
    private const EDITABLE = [DocumentType::GRN, DocumentType::OUT, DocumentType::TRF, DocumentType::OPN];

    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Supervisor);
    }

    public function view(User $user, Document $document): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Document $document): bool
    {
        return $this->viewAny($user)
            && $document->status === DocumentStatus::Draft
            && in_array($document->type, self::EDITABLE, true);
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->update($user, $document)
            && ! $document->lines()->where('qty_scanned', '>', 0)->exists()
            && ! $document->opnameLocations()->where('status', '!=', 'belum')->exists();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function cancel(User $user, Document $document): bool
    {
        return $this->viewAny($user) && $document->status->isOpen() && $document->type !== DocumentType::ADJ;
    }

    public function reverse(User $user, Document $document): bool
    {
        return $this->viewAny($user)
            && $document->status === DocumentStatus::Selesai
            && $document->type !== DocumentType::OPN
            && ! Document::query()->where('reversal_of_id', $document->id)->exists();
    }

    /** Aturan 5: approval selisih opname hanya Supervisor (Admin pun tidak). */
    public function approve(User $user, Document $document): bool
    {
        return $user->hasRole(UserRole::Supervisor)
            && $document->type === DocumentType::OPN
            && $document->status === DocumentStatus::Proses
            && $document->submitted_at !== null;
    }
}
