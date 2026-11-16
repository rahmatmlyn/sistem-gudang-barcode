<?php

namespace App\Filament\Resources\OutboundOrders\Pages;

use App\Filament\Documents\Pages\ListDocuments;
use App\Filament\Resources\OutboundOrders\OutboundOrderResource;

class ListOutboundOrders extends ListDocuments
{
    protected static string $resource = OutboundOrderResource::class;
}
