<?php

namespace App\Filament\Resources\Locations\Tables;

use App\Enums\LocationType;
use App\Filament\Actions\CetakLabelBulkAction;
use App\Services\LabelService;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class LocationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['warehouse', 'parent.parent.parent.parent']))
            ->defaultSort('code')
            ->columns([
                TextColumn::make('code')->label('Kode')->searchable()->sortable(),
                TextColumn::make('path')->label('Path'),
                TextColumn::make('type')->label('Tipe')->badge()->sortable(),
                TextColumn::make('barcode')->label('Barcode')->searchable()->copyable(),
                TextColumn::make('warehouse.code')->label('Gudang')->toggleable(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('type')->label('Tipe')->options(LocationType::class),
                SelectFilter::make('warehouse_id')->label('Gudang')->relationship('warehouse', 'name'),
                TernaryFilter::make('is_active')->label('Aktif'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                CetakLabelBulkAction::make('label-lokasi', fn (Collection $locations, int $copies) => app(LabelService::class)
                    ->locationPdf($locations, $copies)),
            ]);
    }
}
