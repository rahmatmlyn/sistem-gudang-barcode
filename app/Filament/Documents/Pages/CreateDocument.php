<?php

namespace App\Filament\Documents\Pages;

use App\Enums\DocumentStatus;
use App\Services\DocumentNumberService;
use Filament\Resources\Pages\CreateRecord;

/** Nomor otomatis per tipe (aturan 6), status draft, pembuat = user login. */
abstract class CreateDocument extends CreateRecord
{
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $type = static::getResource()::documentType();

        return [
            ...$data,
            'type' => $type,
            'status' => DocumentStatus::Draft,
            'number' => app(DocumentNumberService::class)->next($type),
            'created_by' => auth()->id(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
