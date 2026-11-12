<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DocumentStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Proses = 'proses';
    case Selesai = 'selesai';
    case Batal = 'batal';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Proses => 'Proses',
            self::Selesai => 'Selesai',
            self::Batal => 'Batal',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Proses => 'warning',
            self::Selesai => 'success',
            self::Batal => 'danger',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Draft, self::Proses], true);
    }

    /** @return list<string> */
    public static function openValues(): array
    {
        return [self::Draft->value, self::Proses->value];
    }
}
