<?php

namespace App\Reports;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\Item;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** Angka empat kartu dashboard (wireframe 04). */
final class DashboardSummary
{
    /** @return array{sku_aktif: int, di_bawah_minimum: int, dokumen_berjalan: int, menunggu_approval: int} */
    public static function get(): array
    {
        return [
            'sku_aktif' => Item::query()->where('is_active', true)->count(),
            'di_bawah_minimum' => self::belowMinimum()->count(),
            'dokumen_berjalan' => Document::query()->open()->count(),
            'menunggu_approval' => Document::query()->ofType(DocumentType::OPN)
                ->where('status', DocumentStatus::Proses)->whereNotNull('submitted_at')->count(),
        ];
    }

    /** Barang aktif dengan stok minimum > 0 dan stok total ≤ minimum; kolom tambahan: stok. */
    public static function belowMinimum(): Builder
    {
        $stock = DB::table('stock_balances')->selectRaw('COALESCE(SUM(qty), 0)')->whereColumn('stock_balances.item_id', 'items.id');

        return Item::query()
            ->select('items.*')
            ->selectSub($stock, 'stok')
            ->where('is_active', true)
            ->where('min_stock', '>', 0)
            ->whereRaw('('.$stock->toSql().') <= items.min_stock', $stock->getBindings());
    }
}
