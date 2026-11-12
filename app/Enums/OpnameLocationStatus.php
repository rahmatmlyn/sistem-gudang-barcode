<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum OpnameLocationStatus: string implements HasLabel
{
    case Belum = 'belum';
    case Selesai = 'selesai';
    case Kosong = 'kosong';

    public function getLabel(): string
    {
        return match ($this) {
            self::Belum => 'Belum dihitung',
            self::Selesai => 'Selesai',
            self::Kosong => 'Kosong',
        };
    }
}
