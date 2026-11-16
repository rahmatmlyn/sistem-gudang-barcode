<?php

namespace App\Filament\Widgets;

use App\Reports\DashboardSummary;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class StokMinimumTable extends TableWidget
{
    protected static ?int $sort = 3;

    protected static ?string $heading = 'Stok di bawah minimum';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => DashboardSummary::belowMinimum())
            ->defaultSort('name')
            ->paginated([5])
            ->columns([
                TextColumn::make('name')->label('Barang')->description(fn ($record) => $record->sku),
                TextColumn::make('stok')->label('Stok')->numeric()->color('danger'),
                TextColumn::make('min_stock')->label('Minimum')->numeric(),
            ]);
    }
}
