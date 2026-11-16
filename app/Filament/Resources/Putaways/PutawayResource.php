<?php

namespace App\Filament\Resources\Putaways;

use App\Enums\DocumentType;
use App\Filament\Documents\DocumentResource;
use App\Filament\Resources\Putaways\Pages\ListPutaways;
use App\Filament\Resources\Putaways\Pages\ViewPutaway;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class PutawayResource extends DocumentResource
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?int $navigationSort = 2;

    public static function documentType(): DocumentType
    {
        return DocumentType::PUT;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPutaways::route('/'),
            'view' => ViewPutaway::route('/{record}'),
        ];
    }
}
