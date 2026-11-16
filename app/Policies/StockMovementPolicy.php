<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\StockMovement;
use App\Models\User;

/** Aturan 3: ledger hanya bisa dibaca; tidak ada yang bisa membuat, mengubah, atau menghapus lewat panel. */
class StockMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Supervisor);
    }

    public function view(User $user, StockMovement $movement): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, StockMovement $movement): bool
    {
        return false;
    }

    public function delete(User $user, StockMovement $movement): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
