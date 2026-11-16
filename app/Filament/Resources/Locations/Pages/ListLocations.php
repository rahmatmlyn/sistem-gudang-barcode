<?php

namespace App\Filament\Resources\Locations\Pages;

use App\Filament\Actions\ImportMasterAction;
use App\Filament\Resources\Locations\LocationResource;
use App\Imports\LocationRowImporter;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLocations extends ListRecords
{
    protected static string $resource = LocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ImportMasterAction::make('importLokasi', 'Import lokasi', LocationRowImporter::class, LocationResource::class),
        ];
    }
}
