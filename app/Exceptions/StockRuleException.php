<?php

namespace App\Exceptions;

use DomainException;

/**
 * Pelanggaran aturan bisnis stok. errorCode() dan httpStatus() dipakai API scan (M6) → ubah keduanya bersama kontrak API.
 */
abstract class StockRuleException extends DomainException
{
    abstract public function errorCode(): string;

    public function httpStatus(): int
    {
        return 422;
    }
}
