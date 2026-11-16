<?php

namespace App\Filament\Resources\OutboundOrders;

use App\Enums\DocumentType;
use App\Filament\Documents\DocumentResource;
use App\Filament\Resources\OutboundOrders\Pages\CreateOutboundOrder;
use App\Filament\Resources\OutboundOrders\Pages\EditOutboundOrder;
use App\Filament\Resources\OutboundOrders\Pages\ListOutboundOrders;
use App\Filament\Resources\OutboundOrders\Pages\ViewOutboundOrder;
use App\Filament\Resources\OutboundOrders\Schemas\OutboundOrderForm;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class OutboundOrderResource extends DocumentResource
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?int $navigationSort = 3;

    public static function documentType(): DocumentType
    {
        return DocumentType::OUT;
    }

    public static function form(Schema $schema): Schema
    {
        return OutboundOrderForm::configure($schema);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOutboundOrders::route('/'),
            'create' => CreateOutboundOrder::route('/create'),
            'view' => ViewOutboundOrder::route('/{record}'),
            'edit' => EditOutboundOrder::route('/{record}/edit'),
        ];
    }
}
