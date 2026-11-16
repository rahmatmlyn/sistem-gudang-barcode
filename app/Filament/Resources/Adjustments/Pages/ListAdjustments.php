<?php

namespace App\Filament\Resources\Adjustments\Pages;

use App\Filament\Documents\Pages\ListDocuments;
use App\Filament\Resources\Adjustments\AdjustmentResource;

class ListAdjustments extends ListDocuments
{
    protected static string $resource = AdjustmentResource::class;
}
