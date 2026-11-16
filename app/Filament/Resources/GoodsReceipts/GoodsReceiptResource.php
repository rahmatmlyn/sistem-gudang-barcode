<?php

namespace App\Filament\Resources\GoodsReceipts;

use App\Enums\DocumentType;
use App\Filament\Documents\DocumentResource;
use App\Filament\Resources\GoodsReceipts\Pages\CreateGoodsReceipt;
use App\Filament\Resources\GoodsReceipts\Pages\EditGoodsReceipt;
use App\Filament\Resources\GoodsReceipts\Pages\ListGoodsReceipts;
use App\Filament\Resources\GoodsReceipts\Pages\ViewGoodsReceipt;
use App\Filament\Resources\GoodsReceipts\Schemas\GoodsReceiptForm;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class GoodsReceiptResource extends DocumentResource
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static ?int $navigationSort = 1;

    public static function documentType(): DocumentType
    {
        return DocumentType::GRN;
    }

    public static function form(Schema $schema): Schema
    {
        return GoodsReceiptForm::configure($schema);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGoodsReceipts::route('/'),
            'create' => CreateGoodsReceipt::route('/create'),
            'view' => ViewGoodsReceipt::route('/{record}'),
            'edit' => EditGoodsReceipt::route('/{record}/edit'),
        ];
    }
}
