<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Per pasangan barang–lokasi: Σ masuk − Σ keluar di stock_movements harus sama dengan stock_balances.qty. */
class LedgerVerifier
{
    /** @return Collection<int, object{sku: string, lokasi: string, ledger: int, saldo: int}> */
    public function differences(): Collection
    {
        return collect(DB::select(<<<'SQL'
            WITH ledger AS (
                SELECT item_id, location_id, SUM(qty) AS qty FROM (
                    SELECT item_id, to_location_id AS location_id, qty FROM stock_movements WHERE to_location_id IS NOT NULL
                    UNION ALL
                    SELECT item_id, from_location_id, -qty FROM stock_movements WHERE from_location_id IS NOT NULL
                ) arus GROUP BY item_id, location_id
            )
            SELECT items.sku, locations.code AS lokasi, COALESCE(l.qty, 0)::bigint AS ledger, COALESCE(b.qty, 0)::bigint AS saldo
            FROM ledger l
            FULL OUTER JOIN stock_balances b ON b.item_id = l.item_id AND b.location_id = l.location_id
            JOIN items ON items.id = COALESCE(l.item_id, b.item_id)
            JOIN locations ON locations.id = COALESCE(l.location_id, b.location_id)
            WHERE COALESCE(l.qty, 0) <> COALESCE(b.qty, 0)
            ORDER BY items.sku, locations.code
        SQL))->map(function (object $row) {
            $row->ledger = (int) $row->ledger;
            $row->saldo = (int) $row->saldo;

            return $row;
        });
    }

    public function negativeBalances(): int
    {
        return DB::table('stock_balances')->where('qty', '<', 0)->count();
    }
}
