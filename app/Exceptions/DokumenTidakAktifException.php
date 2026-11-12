<?php

namespace App\Exceptions;

class DokumenTidakAktifException extends StockRuleException
{
    public function errorCode(): string
    {
        return 'DOKUMEN_TIDAK_AKTIF';
    }
}
