<?php

namespace App\Filament\Resources\Warehouses\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WarehousesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('code')
            ->columns([
                TextColumn::make('code')->label('Kode')->searchable()->sortable(),
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('address')->label('Alamat')->limit(60)->toggleable(),
                TextColumn::make('locations_count')->label('Lokasi')->counts('locations'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
