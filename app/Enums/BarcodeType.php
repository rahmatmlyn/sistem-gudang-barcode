<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum BarcodeType: string implements HasLabel
{
    case Ean13 = 'EAN-13';
    case Code128 = 'Code 128';
    case Qr = 'QR';

    public function getLabel(): string
    {
        return $this->value;
    }
}
