<?php

namespace App\Filament\Resources\Items\Pages;

use App\Filament\Actions\ImportMasterAction;
use App\Filament\Resources\Items\ItemResource;
use App\Imports\ItemBarcodeRowImporter;
use App\Imports\ItemRowImporter;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListItems extends ListRecords
{
    protected static string $resource = ItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ImportMasterAction::make('importBarang', 'Import barang', ItemRowImporter::class, ItemResource::class),
            ImportMasterAction::make('importBarcode', 'Import barcode', ItemBarcodeRowImporter::class, ItemResource::class),
        ];
    }
}
