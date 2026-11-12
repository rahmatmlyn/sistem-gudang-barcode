<?php

namespace App\Exceptions;

class StokTidakCukupException extends StockRuleException
{
    public function __construct(
        public readonly int $itemId,
        public readonly int $locationId,
        public readonly int $available,
        public readonly int $requested,
        string $message = '',
    ) {
        parent::__construct($message ?: "Stok tidak cukup: tersedia {$available}, dibutuhkan {$requested}.");
    }

    public function errorCode(): string
    {
        return 'STOK_KURANG';
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
