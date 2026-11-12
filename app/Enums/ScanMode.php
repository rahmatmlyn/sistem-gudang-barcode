<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ScanMode: string implements HasLabel
{
    case GRN = 'GRN';
    case PUT = 'PUT';
    case OUT = 'OUT';
    case PACK = 'PACK';
    case TRF = 'TRF';
    case OPN = 'OPN';

    public function getLabel(): string
    {
        return match ($this) {
            self::GRN => 'Penerimaan',
            self::PUT => 'Putaway',
            self::OUT => 'Picking',
            self::PACK => 'Packing',
            self::TRF => 'Transfer',
            self::OPN => 'Stock Opname',
        };
    }

    public function documentType(): DocumentType
    {
        return match ($this) {
            self::PACK => DocumentType::OUT,
            default => DocumentType::from($this->value),
        };
    }
}
