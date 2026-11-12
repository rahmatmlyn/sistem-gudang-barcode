<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case Admin = 'admin';
    case Supervisor = 'supervisor';
    case Staf = 'staf';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Supervisor => 'Supervisor',
            self::Staf => 'Staf Gudang',
        };
    }

    public function bisaAksesPanel(): bool
    {
        return $this !== self::Staf;
    }

    public function halamanAwal(): string
    {
        return $this->bisaAksesPanel() ? '/admin' : '/scan';
    }
}
