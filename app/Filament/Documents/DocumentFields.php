<?php

namespace App\Filament\Documents;

use App\Models\Item;
use App\Models\Warehouse;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;

/** Field form yang dipakai bersama oleh resource dokumen. */
final class DocumentFields
{
    public static function warehouse(): Select
    {
        return Select::make('warehouse_id')->label('Gudang')->required()->live()
            ->options(fn () => Warehouse::query()->orderBy('code')->pluck('name', 'id')->all())
            ->default(fn () => Warehouse::query()->count() === 1 ? Warehouse::query()->value('id') : null);
    }

    public static function notes(): Textarea
    {
        return Textarea::make('notes')->label('Catatan')->rows(2)->columnSpanFull();
    }

    public static function item(): Select
    {
        return Select::make('item_id')->label('Barang')->required()->distinct()
            ->relationship('item', 'sku', fn (Builder $query) => $query->where('is_active', true))
            ->getOptionLabelFromRecordUsing(fn (Item $item) => "{$item->sku} — {$item->name}")
            ->searchable(['sku', 'name']);
    }

    /** Lokasi dalam baris repeater, dibatasi ke gudang dokumen. */
    public static function location(string $column, string $relationship, string $label): Select
    {
        return Select::make($column)->label($label)->required()
            ->relationship($relationship, 'code', fn (Builder $query, Get $get) => $query
                ->where('is_active', true)
                ->when($get('../../warehouse_id'), fn (Builder $query, $warehouseId) => $query->where('warehouse_id', $warehouseId)))
            ->searchable();
    }

    public static function qty(string $label = 'Qty (satuan dasar)', int $min = 1): TextInput
    {
        return TextInput::make('qty_planned')->label($label)->integer()->minValue($min)->required()->default($min);
    }
}
