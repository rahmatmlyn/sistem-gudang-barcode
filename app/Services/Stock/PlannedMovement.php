<?php

namespace App\Services\Stock;

final readonly class PlannedMovement
{
    public function __construct(
        public int $itemId,
        public ?int $fromLocationId,
        public ?int $toLocationId,
        public int $qty,
        public int $documentLineId,
    ) {}
}
