<?php

namespace App\Filament\Resources\GoodsReceipts\Pages;

use App\Filament\Documents\Pages\ListDocuments;
use App\Filament\Resources\GoodsReceipts\GoodsReceiptResource;

class ListGoodsReceipts extends ListDocuments
{
    protected static string $resource = GoodsReceiptResource::class;
}
