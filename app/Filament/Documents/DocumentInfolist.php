<?php

namespace App\Filament\Documents;

use App\Models\Document;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DocumentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Dokumen')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('number')->label('Nomor'),
                        TextEntry::make('status')->label('Status')->badge(),
                        TextEntry::make('warehouse.name')->label('Gudang'),
                        TextEntry::make('partner')->label('Mitra')->placeholder('-'),
                        TextEntry::make('reference')->label('Referensi')->placeholder('-'),
                        TextEntry::make('creator.name')->label('Dibuat oleh'),
                        TextEntry::make('created_at')->label('Dibuat')->dateTime('d M Y H:i'),
                        TextEntry::make('submitted_at')->label('Diajukan')->dateTime('d M Y H:i')->placeholder('-'),
                        TextEntry::make('completed_at')->label('Selesai')->dateTime('d M Y H:i')->placeholder('-'),
                        TextEntry::make('completer.name')->label('Diselesaikan oleh')->placeholder('-'),
                        TextEntry::make('approver.name')->label('Disetujui oleh')->placeholder('-'),
                        TextEntry::make('approval_reason')->label('Alasan')->placeholder('-'),
                        TextEntry::make('reversalOf.number')->label('Membalik dokumen')->placeholder('-'),
                        TextEntry::make('relatedDocument.number')->label('Dokumen terkait')->placeholder('-'),
                        TextEntry::make('pembalik')->label('Dibalik oleh')->placeholder('-')
                            ->state(fn (Document $record) => Document::query()->where('reversal_of_id', $record->id)->value('number')),
                        TextEntry::make('notes')->label('Catatan')->placeholder('-')->columnSpanFull(),
                    ]),
            ]);
    }
}
