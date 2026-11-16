<?php

namespace App\Filament\Resources\StockOpnames\RelationManagers;

use App\Models\DocumentLine;
use App\Models\Location;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Fisik vs sistem per lokasi–barang. Hanya di panel: qty sistem tidak pernah dikirim ke /scan (UAT-14). */
class HasilHitungRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Hasil hitung';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['item', 'fromLocation']))
            ->defaultSort(fn (Builder $query) => $query
                ->orderBy(Location::query()->select('code')->whereColumn('locations.id', 'document_lines.from_location_id'))
                ->orderBy('document_lines.item_id'))
            ->columns([
                TextColumn::make('fromLocation.code')->label('Lokasi'),
                TextColumn::make('item.sku')->label('SKU')->searchable(),
                TextColumn::make('item.name')->label('Barang')->searchable(),
                TextColumn::make('qty_scanned')->label('Fisik')->numeric(),
                TextColumn::make('qty_system')->label('Sistem')->numeric()->placeholder('Belum selesai'),
                TextColumn::make('selisih')->label('Selisih')
                    ->state(fn (DocumentLine $record) => $record->qty_system === null ? null : $record->qty_scanned - $record->qty_system)
                    ->color(fn (?int $state) => match (true) {
                        $state === null, $state === 0 => null,
                        $state < 0 => 'danger',
                        default => 'warning',
                    })
                    ->placeholder('-'),
            ])
            ->filters([
                Filter::make('hanya_selisih')->label('Hanya yang selisih')
                    ->query(fn (Builder $query) => $query->whereNotNull('qty_system')->whereColumn('qty_scanned', '!=', 'qty_system')),
            ]);
    }
}
