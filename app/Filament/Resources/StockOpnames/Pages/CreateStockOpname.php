<?php

namespace App\Filament\Resources\StockOpnames\Pages;

use App\Filament\Documents\Pages\CreateDocument;
use App\Filament\Resources\StockOpnames\StockOpnameResource;
use App\Services\OpnameService;

class CreateStockOpname extends CreateDocument
{
    protected static string $resource = StockOpnameResource::class;

    protected function afterCreate(): void
    {
        app(OpnameService::class)->setLocations($this->getRecord(), $this->data['lokasi'] ?? []);
    }
}
