<?php

namespace App\Filament\Documents\Pages;

use App\Filament\Documents\DocumentActions;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

abstract class ViewDocument extends ViewRecord
{
    protected function getHeaderActions(): array
    {
        return [
            DocumentActions::rilis(),
            DocumentActions::ajukan(),
            DocumentActions::setujui(),
            DocumentActions::tolak(),
            DocumentActions::balik(),
            EditAction::make(),
            DeleteAction::make(),
            DocumentActions::batalkan(),
        ];
    }
}
