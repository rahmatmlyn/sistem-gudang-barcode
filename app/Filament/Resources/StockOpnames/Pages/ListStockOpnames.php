<?php

namespace App\Filament\Resources\StockOpnames\Pages;

use App\Filament\Documents\Pages\ListDocuments;
use App\Filament\Resources\StockOpnames\StockOpnameResource;

class ListStockOpnames extends ListDocuments
{
    protected static string $resource = StockOpnameResource::class;
}
