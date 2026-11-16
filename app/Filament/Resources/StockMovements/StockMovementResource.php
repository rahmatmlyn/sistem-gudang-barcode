<?php

namespace App\Filament\Resources\StockMovements;

use App\Enums\DocumentType;
use App\Filament\Resources\StockMovements\Pages\ListStockMovements;
use App\Models\StockMovement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Ledger stok, hanya baca (aturan 3). Koreksi lewat transaksi balik di dokumen asalnya. */
class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static string|UnitEnum|null $navigationGroup = 'Dokumen';

    protected static ?int $navigationSort = 7;

    protected static ?string $modelLabel = 'mutasi stok';

    protected static ?string $pluralModelLabel = 'mutasi stok';

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['item', 'fromLocation', 'toLocation', 'documentLine.document', 'user']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d M Y H:i:s')->sortable(),
                TextColumn::make('type')->label('Tipe')->badge(),
                TextColumn::make('documentLine.document.number')->label('Dokumen')->placeholder('-'),
                TextColumn::make('item.sku')->label('SKU')->searchable(),
                TextColumn::make('item.name')->label('Barang')->searchable()->toggleable(),
                TextColumn::make('fromLocation.code')->label('Dari')->placeholder('-'),
                TextColumn::make('toLocation.code')->label('Ke')->placeholder('-'),
                TextColumn::make('qty')->label('Qty')->numeric(),
                TextColumn::make('user.name')->label('User')->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('type')->label('Tipe')->options(DocumentType::class),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListStockMovements::route('/')];
    }
}
