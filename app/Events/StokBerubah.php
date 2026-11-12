<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Dikirim setelah transaksi stok commit; dipakai notifikasi stok minimum (M8). */
class StokBerubah implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /** @param  list<int>  $itemIds */
    public function __construct(public readonly array $itemIds) {}
}
