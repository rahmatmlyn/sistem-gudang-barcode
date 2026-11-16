<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/** Pengelolaan pengguna hanya untuk Admin (K6). */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function view(User $user, User $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, User $record): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, User $record): bool
    {
        return $this->viewAny($user) && ! $user->is($record) && ! $record->isReferenced();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
