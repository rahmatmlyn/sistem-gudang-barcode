<?php

namespace App\Filament\Resources\Adjustments;

use App\Enums\DocumentType;
use App\Filament\Documents\DocumentResource;
use App\Filament\Resources\Adjustments\Pages\ListAdjustments;
use App\Filament\Resources\Adjustments\Pages\ViewAdjustment;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class AdjustmentResource extends DocumentResource
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?int $navigationSort = 6;

    public static function documentType(): DocumentType
    {
        return DocumentType::ADJ;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdjustments::route('/'),
            'view' => ViewAdjustment::route('/{record}'),
        ];
    }
}
