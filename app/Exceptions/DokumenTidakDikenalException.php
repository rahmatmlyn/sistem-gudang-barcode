<?php

namespace App\Exceptions;

class DokumenTidakDikenalException extends StockRuleException
{
    public function errorCode(): string
    {
        return 'DOKUMEN_TIDAK_DIKENAL';
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
