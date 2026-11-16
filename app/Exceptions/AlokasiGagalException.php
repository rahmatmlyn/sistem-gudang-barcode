<?php

namespace App\Exceptions;

/** Pesanan keluar tidak bisa dirilis karena stok yang belum dijanjikan ke pesanan lain kurang. */
class AlokasiGagalException extends StockRuleException
{
    public function errorCode(): string
    {
        return 'STOK_KURANG';
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
