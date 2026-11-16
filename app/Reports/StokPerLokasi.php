<?php

namespace App\Reports;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Item;
use App\Models\Location;
use App\Models\StockBalance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** Stok per lokasi (FR-15): saldo, qty dicadangkan scan dokumen terbuka (K1), dan qty tersedia. */
final class StokPerLokasi
{
    public function __construct(public readonly ?int $zonaId = null, public readonly ?int $locationId = null, public readonly ?int $itemId = null) {}

    public function query(): Builder
    {
        $reservingTypes = collect(DocumentType::cases())->filter->reservesSourceStock()->map->value->all();
        $reserved = DB::table('document_lines')
            ->join('documents', 'documents.id', '=', 'document_lines.document_id')
            ->whereColumn('document_lines.item_id', 'stock_balances.item_id')
            ->whereColumn('document_lines.from_location_id', 'stock_balances.location_id')
            ->whereIn('documents.status', DocumentStatus::openValues())
            ->whereIn('documents.type', $reservingTypes)
            ->selectRaw('COALESCE(SUM(document_lines.qty_scanned), 0)');

        return StockBalance::query()
            ->join('locations', 'locations.id', '=', 'stock_balances.location_id')
            ->join('items', 'items.id', '=', 'stock_balances.item_id')
            ->where('stock_balances.qty', '!=', 0)
            ->when($this->zonaId, fn (Builder $query, int $zonaId) => $query->whereIn('stock_balances.location_id', $this->zonaLocationIds($zonaId)))
            ->when($this->locationId, fn (Builder $query, int $locationId) => $query->where('stock_balances.location_id', $locationId))
            ->when($this->itemId, fn (Builder $query, int $itemId) => $query->where('stock_balances.item_id', $itemId))
            ->select('stock_balances.id', 'stock_balances.qty', 'locations.code AS location_code', 'items.sku', 'items.name AS item_name', 'items.base_unit')
            ->selectSub($reserved, 'dipesan')
            ->orderBy('locations.code')
            ->orderBy('items.sku');
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ['Lokasi', 'SKU', 'Barang', 'Satuan', 'Saldo', 'Dipesan', 'Tersedia'];
    }

    /** @return list<list<string|int>> */
    public function exportRows(): array
    {
        return $this->query()->toBase()->get()->map(fn ($row) => [
            $row->location_code, $row->sku, $row->item_name, $row->base_unit,
            (int) $row->qty, (int) $row->dipesan, (int) $row->qty - (int) $row->dipesan,
        ])->all();
    }

    public function filterDescription(): string
    {
        $parts = array_filter([
            $this->zonaId ? 'Zona '.Location::query()->whereKey($this->zonaId)->value('code') : null,
            $this->locationId ? 'Lokasi '.Location::query()->whereKey($this->locationId)->value('code') : null,
            $this->itemId ? 'Barang '.Item::query()->whereKey($this->itemId)->value('sku') : null,
        ]);

        return $parts === [] ? 'Semua lokasi' : implode(' · ', $parts);
    }

    /** @return list<int> */
    private function zonaLocationIds(int $zonaId): array
    {
        $zona = Location::query()->find($zonaId);

        return $zona === null ? [] : [$zona->id, ...$zona->descendantIds()];
    }
}
