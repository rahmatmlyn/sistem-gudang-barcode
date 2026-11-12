<?php

namespace App\Exceptions;

class QtyTidakCocokException extends StockRuleException
{
    public function errorCode(): string
    {
        return 'QTY_TIDAK_COCOK';
    }
}
