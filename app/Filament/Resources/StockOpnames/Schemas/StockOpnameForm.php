<?php

namespace App\Filament\Resources\StockOpnames\Schemas;

use App\Filament\Documents\DocumentFields;
use App\Models\Location;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/** OPN: pilih lokasi/zona; turunannya ikut dihitung (OpnameService::setLocations). */
class StockOpnameForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Stock opname')->columns(2)->schema([
                DocumentFields::warehouse(),
                Select::make('lokasi')->label('Lokasi atau zona')->multiple()->required()->searchable()
                    ->helperText('Memilih zona atau rak otomatis menyertakan semua lokasi di bawahnya.')
                    ->options(fn (Get $get) => Location::query()
                        ->when($get('warehouse_id'), fn ($query, $warehouseId) => $query->where('warehouse_id', $warehouseId))
                        ->where('is_active', true)->orderBy('code')->pluck('code', 'id')->all())
                    ->dehydrated(false),
                DocumentFields::notes(),
            ]),
        ]);
    }
}
