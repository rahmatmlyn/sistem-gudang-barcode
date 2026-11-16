<?php

namespace App\Filament\Resources\OutboundOrders\Pages;

use App\Filament\Documents\Pages\CreateDocument;
use App\Filament\Resources\OutboundOrders\OutboundOrderResource;

class CreateOutboundOrder extends CreateDocument
{
    protected static string $resource = OutboundOrderResource::class;
}
