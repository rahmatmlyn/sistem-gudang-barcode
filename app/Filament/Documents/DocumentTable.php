<?php

namespace App\Filament\Documents;

use App\Enums\DocumentStatus;
use App\Models\Document;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Tabel pemantauan dokumen: status diperbarui otomatis tiap 10 detik. */
class DocumentTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('creator')
                ->withSum('lines', 'qty_planned')->withSum('lines', 'qty_scanned'))
            ->poll('10s')
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('number')->label('Nomor')->searchable()->sortable(),
                TextColumn::make('status')->label('Status')->badge()
                    ->description(fn (Document $record) => $record->submitted_at && $record->status === DocumentStatus::Proses ? 'Menunggu approval' : null),
                TextColumn::make('partner')->label('Mitra')->searchable()->toggleable(),
                TextColumn::make('reference')->label('Referensi')->searchable()->toggleable(),
                TextColumn::make('progres')->label('Scan / rencana')
                    ->state(fn (Document $record) => ((int) $record->lines_sum_qty_scanned).' / '.((int) $record->lines_sum_qty_planned)),
                TextColumn::make('creator.name')->label('Dibuat oleh')->toggleable(),
                TextColumn::make('created_at')->label('Dibuat')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('completed_at')->label('Selesai')->dateTime('d M Y H:i')->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(DocumentStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
