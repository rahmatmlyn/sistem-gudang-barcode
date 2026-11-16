<?php

namespace App\Reports;

use App\Models\Item;
use App\Models\Location;
use App\Models\StockMovement;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Kartu stok (FR-15, UAT-20): saldo awal + mutasi dengan saldo berjalan + saldo akhir.
 * Tanpa filter lokasi, pindah antar lokasi tercatat masuk dan keluar sekaligus (netto 0).
 * Saldo berjalan dihitung window function di atas seluruh rentang, jadi tetap benar saat dipaginasi.
 */
final class KartuStok
{
    public readonly CarbonInterface $from;

    public readonly CarbonInterface $to;

    public function __construct(public readonly int $itemId, public readonly ?int $locationId, CarbonInterface $from, CarbonInterface $to)
    {
        $this->from = $from->copy()->startOfDay();
        $this->to = $to->copy()->endOfDay();
    }

    public function saldoAwal(): int
    {
        return $this->sum($this->movements()->where('created_at', '<', $this->from));
    }

    public function saldoAkhir(): int
    {
        return $this->saldoAwal() + $this->sum($this->inRange());
    }

    public function query(): Builder
    {
        [$in, $out, $bindings] = $this->effect();

        return $this->inRange()
            ->with(['documentLine.document', 'fromLocation', 'toLocation'])
            ->select('stock_movements.*')
            ->selectRaw("{$in} AS masuk, {$out} AS keluar", [...$bindings, ...$bindings])
            ->selectRaw("? + SUM({$in} - {$out}) OVER (ORDER BY stock_movements.created_at, stock_movements.id) AS saldo",
                [$this->saldoAwal(), ...$bindings, ...$bindings])
            ->orderBy('stock_movements.created_at')
            ->orderBy('stock_movements.id');
    }

    /** @return Collection<int, StockMovement> masuk/keluar/saldo sebagai int */
    public function rows(): Collection
    {
        return $this->query()->get()->each(function (StockMovement $row) {
            $row->masuk = (int) $row->masuk;
            $row->keluar = (int) $row->keluar;
            $row->saldo = (int) $row->saldo;
        });
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ['Tanggal', 'Dokumen', 'Tipe', 'Dari', 'Ke', 'Masuk', 'Keluar', 'Saldo'];
    }

    /** @return list<list<string|int>> */
    public function exportRows(): array
    {
        $rows = [['Saldo awal', '', '', '', '', '', '', $this->saldoAwal()]];
        foreach ($this->rows() as $row) {
            $rows[] = [
                $row->created_at->format('d/m/Y H:i'), $row->documentLine?->document?->number ?? '', $row->type->value,
                $row->fromLocation?->code ?? '', $row->toLocation?->code ?? '', $row->masuk, $row->keluar, $row->saldo,
            ];
        }
        $rows[] = ['Saldo akhir', '', '', '', '', '', '', $this->saldoAkhir()];

        return $rows;
    }

    public function description(): string
    {
        $item = Item::query()->find($this->itemId);
        $location = $this->locationId ? Location::query()->whereKey($this->locationId)->value('code') : 'semua lokasi';

        return "{$item?->sku} {$item?->name} · {$location} · {$this->from->format('d/m/Y')} – {$this->to->format('d/m/Y')}";
    }

    private function inRange(): Builder
    {
        return $this->movements()->whereBetween('stock_movements.created_at', [$this->from, $this->to]);
    }

    private function movements(): Builder
    {
        return StockMovement::query()
            ->where('stock_movements.item_id', $this->itemId)
            ->when($this->locationId, fn (Builder $query, int $locationId) => $query->where(fn (Builder $query) => $query
                ->where('stock_movements.from_location_id', $locationId)->orWhere('stock_movements.to_location_id', $locationId)));
    }

    private function sum(Builder $query): int
    {
        [$in, $out, $bindings] = $this->effect();

        return (int) $query->toBase()->selectRaw("COALESCE(SUM({$in} - {$out}), 0) AS netto", [...$bindings, ...$bindings])->value('netto');
    }

    /** @return array{0: string, 1: string, 2: list<int>} ekspresi SQL masuk, keluar, dan binding untuk masing-masing */
    private function effect(): array
    {
        return $this->locationId === null
            ? ['CASE WHEN stock_movements.to_location_id IS NOT NULL THEN stock_movements.qty ELSE 0 END',
                'CASE WHEN stock_movements.from_location_id IS NOT NULL THEN stock_movements.qty ELSE 0 END', []]
            : ['CASE WHEN stock_movements.to_location_id = ? THEN stock_movements.qty ELSE 0 END',
                'CASE WHEN stock_movements.from_location_id = ? THEN stock_movements.qty ELSE 0 END', [$this->locationId]];
    }
}
