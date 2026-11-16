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

    /** Menerima variasi penulisan di file import: "EAN-13", "ean13", "code128", "QR Code". */
    public static function fromLabel(?string $text): ?self
    {
        return match (preg_replace('/[^a-z0-9]/', '', strtolower((string) $text))) {
            'ean13' => self::Ean13,
            'code128' => self::Code128,
            'qr', 'qrcode' => self::Qr,
            default => null,
        };
    }
}
