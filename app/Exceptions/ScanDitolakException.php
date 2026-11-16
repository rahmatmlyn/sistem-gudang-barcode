<?php

namespace App\Exceptions;

/** Scan ditolak karena barang/lokasi tidak sesuai dokumen. Kode: LOKASI_SALAH | BARANG_SALAH (kontrak /api/scan). */
class ScanDitolakException extends StockRuleException
{
    public function __construct(private string $errorCode, string $message)
    {
        parent::__construct($message);
    }

    public static function lokasiSalah(string $message): self
    {
        return new self('LOKASI_SALAH', $message);
    }

    public static function barangSalah(string $message): self
    {
        return new self('BARANG_SALAH', $message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
