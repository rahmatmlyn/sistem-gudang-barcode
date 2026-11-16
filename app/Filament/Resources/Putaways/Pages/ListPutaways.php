<?php

namespace App\Filament\Resources\Putaways\Pages;

use App\Filament\Documents\Pages\ListDocuments;
use App\Filament\Resources\Putaways\PutawayResource;

class ListPutaways extends ListDocuments
{
    protected static string $resource = PutawayResource::class;
}
