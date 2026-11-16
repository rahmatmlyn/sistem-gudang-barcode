<?php

namespace App\Filament\Documents\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

abstract class ListDocuments extends ListRecords
{
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
