<?php

namespace App\Services\Scan;

use App\Enums\ScanMode;
use App\Models\Document;
use App\Models\Location;
use App\Models\User;

/** Satu scan yang sudah divalidasi; $document sudah dikunci FOR UPDATE oleh ScanService. */
final readonly class ScanInput
{
    public function __construct(
        public ScanMode $mode,
        public Document $document,
        public ?Location $location,
        public ?Location $toLocation,
        public int $qty,
        public User $user,
    ) {}
}
