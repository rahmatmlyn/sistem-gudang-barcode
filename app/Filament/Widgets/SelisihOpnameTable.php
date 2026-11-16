<?php

namespace App\Filament\Widgets;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Filament\Resources\StockOpnames\StockOpnameResource;
use App\Models\DocumentLine;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class SelisihOpnameTable extends TableWidget
{
    protected static ?int $sort = 2;

    protected static ?string $heading = 'Selisih opname menunggu approval';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => DocumentLine::query()
                ->with(['item', 'fromLocation', 'document'])
                ->whereHas('document', fn (Builder $query) => $query->where('type', DocumentType::OPN)
                    ->where('status', DocumentStatus::Proses)->whereNotNull('submitted_at'))
                ->whereNotNull('qty_system')
                ->whereColumn('qty_scanned', '!=', 'qty_system'))
            ->paginated([5])
            ->columns([
                TextColumn::make('item.name')->label('Barang')->description(fn (DocumentLine $record) => $record->fromLocation?->code),
                TextColumn::make('qty_system')->label('Sistem')->numeric(),
                TextColumn::make('qty_scanned')->label('Fisik')->numeric(),
                TextColumn::make('selisih')->label('Selisih')
                    ->state(fn (DocumentLine $record) => sprintf('%+d', $record->qty_scanned - $record->qty_system))
                    ->color(fn (DocumentLine $record) => $record->qty_scanned < $record->qty_system ? 'danger' : 'warning'),
            ])
            ->recordActions([
                Action::make('tinjau')->label('Tinjau')
                    ->url(fn (DocumentLine $record) => StockOpnameResource::getUrl('view', ['record' => $record->document])),
            ]);
    }
}
