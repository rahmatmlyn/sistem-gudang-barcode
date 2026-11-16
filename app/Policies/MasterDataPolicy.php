<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Barang, lokasi, gudang (K6): Admin kelola, Supervisor hanya lihat, Staf tidak punya akses.
 * Record yang sudah dipakai transaksi tidak bisa dihapus (model::isReferenced()).
 */
class MasterDataPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Supervisor);
    }

    public function view(User $user, Model $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function update(User $user, Model $record): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function delete(User $user, Model $record): bool
    {
        return $user->hasRole(UserRole::Admin) && ! $record->isReferenced();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
