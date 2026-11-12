<?php

namespace App\Services\Stock;

use App\Enums\DocumentType;
use App\Exceptions\StokTidakCukupException;
use App\Models\Item;
use App\Models\Location;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Menulis stock_movements + stock_balances. Hanya dipanggil oleh StockService, di dalam DB::transaction milik pemanggil.
 *
 * Invarian: baris saldo selalu dikunci urut (item_id, location_id) agar dua penyelesaian bersamaan tidak deadlock;
 * semua saldo divalidasi >= 0 sebelum ada yang ditulis, sehingga dokumen ditolak utuh atau diterapkan utuh.
 */
class LedgerWriter
{
    /**
     * @param  list<PlannedMovement>  $movements
     * @return list<int> item_id yang saldonya berubah
     */
    public function apply(array $movements, DocumentType $type, User $by): array
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('LedgerWriter::apply harus dipanggil di dalam DB::transaction.');
        }

        $deltas = $this->deltas($movements);
        $balances = $this->lockBalances($deltas);

        foreach ($deltas as $key => [$itemId, $locationId, $delta]) {
            $current = $balances[$key]->qty ?? 0;
            if ($current + $delta < 0) {
                throw $this->insufficient($itemId, $locationId, $current, -$delta);
            }
        }

        $now = now();
        foreach ($deltas as $key => [$itemId, $locationId, $delta]) {
            StockBalance::query()->whereKey($balances[$key]->id)->update([
                'qty' => $balances[$key]->qty + $delta,
                'updated_at' => $now,
            ]);
        }

        StockMovement::query()->insert(array_map(fn (PlannedMovement $movement) => [
            'item_id' => $movement->itemId,
            'from_location_id' => $movement->fromLocationId,
            'to_location_id' => $movement->toLocationId,
            'qty' => $movement->qty,
            'type' => $type->value,
            'document_line_id' => $movement->documentLineId,
            'user_id' => $by->id,
            'created_at' => $now,
        ], $movements));

        return array_values(array_unique(array_column($deltas, 0)));
    }

    /**
     * @param  list<PlannedMovement>  $movements
     * @return array<string, array{0: int, 1: int, 2: int}> kunci "item:lokasi" → [item_id, location_id, delta], terurut
     */
    private function deltas(array $movements): array
    {
        $deltas = [];
        $add = function (int $itemId, int $locationId, int $delta) use (&$deltas) {
            $key = sprintf('%012d:%012d', $itemId, $locationId);
            $deltas[$key] ??= [$itemId, $locationId, 0];
            $deltas[$key][2] += $delta;
        };

        foreach ($movements as $movement) {
            if ($movement->qty <= 0) {
                throw new LogicException('Qty movement harus lebih dari 0.');
            }
            if ($movement->fromLocationId !== null) {
                $add($movement->itemId, $movement->fromLocationId, -$movement->qty);
            }
            if ($movement->toLocationId !== null) {
                $add($movement->itemId, $movement->toLocationId, $movement->qty);
            }
        }

        ksort($deltas);

        return $deltas;
    }

    /**
     * @param  array<string, array{0: int, 1: int, 2: int}>  $deltas
     * @return array<string, StockBalance>
     */
    private function lockBalances(array $deltas): array
    {
        $now = now();
        StockBalance::query()->insertOrIgnore(array_values(array_map(fn (array $delta) => [
            'item_id' => $delta[0], 'location_id' => $delta[1], 'qty' => 0, 'created_at' => $now, 'updated_at' => $now,
        ], $deltas)));

        $balances = [];
        foreach ($deltas as $key => [$itemId, $locationId]) {
            $balances[$key] = StockBalance::query()
                ->where('item_id', $itemId)
                ->where('location_id', $locationId)
                ->lockForUpdate()
                ->firstOrFail();
        }

        return $balances;
    }

    private function insufficient(int $itemId, int $locationId, int $available, int $requested): StokTidakCukupException
    {
        $sku = Item::query()->whereKey($itemId)->value('sku');
        $location = Location::query()->whereKey($locationId)->value('code');

        return new StokTidakCukupException(
            $itemId, $locationId, $available, $requested,
            "Stok {$sku} di {$location} tidak cukup: tersedia {$available}, dibutuhkan {$requested}.",
        );
    }
}
