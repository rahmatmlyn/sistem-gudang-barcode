<?php

namespace App\Filament\Documents;

use App\Enums\DocumentType;
use App\Models\Location;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Baris dokumen (hanya baca); urut kode lokasi asal = urutan picking (UAT-07). */
class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Baris barang';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        $isOut = $this->getOwnerRecord()->type === DocumentType::OUT;

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['item', 'fromLocation', 'toLocation']))
            ->defaultSort(fn (Builder $query) => $query
                ->orderBy(Location::query()->select('code')->whereColumn('locations.id', 'document_lines.from_location_id'))
                ->orderBy('document_lines.id'))
            ->columns([
                TextColumn::make('fromLocation.code')->label('Asal')->placeholder('-'),
                TextColumn::make('toLocation.code')->label('Tujuan')->placeholder('-'),
                TextColumn::make('item.sku')->label('SKU')->searchable(),
                TextColumn::make('item.name')->label('Barang')->searchable(),
                TextColumn::make('qty_planned')->label('Rencana')->numeric(),
                TextColumn::make('qty_scanned')->label('Scan')->numeric(),
                TextColumn::make('qty_packed')->label('Packing')->numeric()->visible($isOut),
            ]);
    }
}
