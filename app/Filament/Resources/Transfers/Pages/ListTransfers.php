<?php

namespace App\Filament\Resources\Transfers\Pages;

use App\Filament\Documents\Pages\ListDocuments;
use App\Filament\Resources\Transfers\TransferResource;

class ListTransfers extends ListDocuments
{
    protected static string $resource = TransferResource::class;
}
