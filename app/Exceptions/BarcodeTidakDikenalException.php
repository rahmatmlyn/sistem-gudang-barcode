<?php

namespace App\Exceptions;

class BarcodeTidakDikenalException extends StockRuleException
{
    public function errorCode(): string
    {
        return 'BARCODE_TIDAK_DIKENAL';
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
