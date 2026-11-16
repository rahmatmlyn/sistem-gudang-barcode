<?php

namespace App\Filament\Resources\StockOpnames;

use App\Enums\DocumentType;
use App\Filament\Documents\DocumentResource;
use App\Filament\Resources\StockOpnames\Pages\CreateStockOpname;
use App\Filament\Resources\StockOpnames\Pages\EditStockOpname;
use App\Filament\Resources\StockOpnames\Pages\ListStockOpnames;
use App\Filament\Resources\StockOpnames\Pages\ViewStockOpname;
use App\Filament\Resources\StockOpnames\RelationManagers\HasilHitungRelationManager;
use App\Filament\Resources\StockOpnames\RelationManagers\LokasiOpnameRelationManager;
use App\Filament\Resources\StockOpnames\Schemas\StockOpnameForm;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class StockOpnameResource extends DocumentResource
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 5;

    public static function documentType(): DocumentType
    {
        return DocumentType::OPN;
    }

    public static function form(Schema $schema): Schema
    {
        return StockOpnameForm::configure($schema);
    }

    public static function getRelations(): array
    {
        return [HasilHitungRelationManager::class, LokasiOpnameRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockOpnames::route('/'),
            'create' => CreateStockOpname::route('/create'),
            'view' => ViewStockOpname::route('/{record}'),
            'edit' => EditStockOpname::route('/{record}/edit'),
        ];
    }
}
