<?php

namespace App\Filament\Resources\Items\Tables;

use App\Models\Item;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sku')
            ->columns([
                TextColumn::make('sku')->label('SKU')->searchable()->sortable(),
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('category')->label('Kategori')->sortable()->toggleable(),
                TextColumn::make('base_unit')->label('Satuan'),
                TextColumn::make('min_stock')->label('Stok min.')->numeric()->sortable(),
                TextColumn::make('barcodes_count')->label('Barcode')->counts('barcodes'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('category')->label('Kategori')
                    ->options(fn () => Item::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category', 'category')->all()),
                TernaryFilter::make('is_active')->label('Aktif'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
