<?php

namespace App\Exceptions;

class MelebihiRencanaException extends StockRuleException
{
    public function errorCode(): string
    {
        return 'MELEBIHI_PESANAN';
    }
}
