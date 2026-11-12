<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DocumentType: string implements HasLabel
{
    case GRN = 'GRN';
    case PUT = 'PUT';
    case OUT = 'OUT';
    case TRF = 'TRF';
    case OPN = 'OPN';
    case ADJ = 'ADJ';

    public function getLabel(): string
    {
        return match ($this) {
            self::GRN => 'Penerimaan',
            self::PUT => 'Putaway',
            self::OUT => 'Pesanan Keluar',
            self::TRF => 'Transfer',
            self::OPN => 'Stock Opname',
            self::ADJ => 'Penyesuaian',
        };
    }

    /** Tipe yang mengambil stok dari lokasi asal saat di-scan (reservasi, lihat StockService::available). */
    public function reservesSourceStock(): bool
    {
        return in_array($this, [self::PUT, self::OUT, self::TRF], true);
    }
}
