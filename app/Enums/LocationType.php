<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum LocationType: string implements HasLabel
{
    case AreaTerima = 'area_terima';
    case AreaKirim = 'area_kirim';
    case Zona = 'zona';
    case Rak = 'rak';
    case Bin = 'bin';

    public function getLabel(): string
    {
        return match ($this) {
            self::AreaTerima => 'Area terima',
            self::AreaKirim => 'Area kirim',
            self::Zona => 'Zona',
            self::Rak => 'Rak',
            self::Bin => 'Bin',
        };
    }

    /** Menerima label CSV ("area terima") maupun value ("area_terima"). */
    public static function fromLabel(string $text): ?self
    {
        $normalized = str_replace(' ', '_', strtolower(trim($text)));

        return self::tryFrom($normalized);
    }
}
