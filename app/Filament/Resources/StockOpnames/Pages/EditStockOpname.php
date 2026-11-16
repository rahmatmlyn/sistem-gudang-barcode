<?php

namespace App\Filament\Resources\StockOpnames\Pages;

use App\Filament\Documents\Pages\EditDocument;
use App\Filament\Resources\StockOpnames\StockOpnameResource;
use App\Services\OpnameService;

class EditStockOpname extends EditDocument
{
    protected static string $resource = StockOpnameResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [...$data, 'lokasi' => $this->getRecord()->opnameLocations()->pluck('location_id')->all()];
    }

    protected function afterSave(): void
    {
        app(OpnameService::class)->setLocations($this->getRecord(), $this->data['lokasi'] ?? []);
    }
}
