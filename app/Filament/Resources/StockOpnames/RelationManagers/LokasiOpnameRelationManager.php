<?php

namespace App\Filament\Resources\StockOpnames\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Progres hitung per lokasi; hasil yang sudah disimpan tetap ada bila staf terputus (UAT-15). */
class LokasiOpnameRelationManager extends RelationManager
{
    protected static string $relationship = 'opnameLocations';

    protected static ?string $title = 'Lokasi';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['location', 'counter'])
                ->join('locations', 'locations.id', '=', 'opname_locations.location_id')
                ->select('opname_locations.*')
                ->orderBy('locations.code'))
            ->poll('10s')
            ->columns([
                TextColumn::make('location.code')->label('Lokasi'),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('counter.name')->label('Dihitung oleh')->placeholder('-'),
                TextColumn::make('counted_at')->label('Waktu')->dateTime('d M Y H:i')->placeholder('-'),
            ]);
    }
}
