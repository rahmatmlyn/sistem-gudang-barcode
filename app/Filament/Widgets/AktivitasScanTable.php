<?php

namespace App\Filament\Widgets;

use App\Models\ScanLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class AktivitasScanTable extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Aktivitas scan terbaru';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => ScanLog::query()->with(['user', 'document'])->where('success', true)->latest('id'))
            ->poll('10s')
            ->paginated([10])
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('H.i'),
                TextColumn::make('user.name')->label('Staf'),
                TextColumn::make('message')->label('Aktivitas')->wrap(),
                TextColumn::make('document.number')->label('Dokumen')->placeholder('-'),
            ]);
    }
}
