<?php

namespace App\Filament\Resources\Warehouses\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WarehouseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Gudang')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')->label('Kode')->required()->maxLength(20)->unique(ignoreRecord: true),
                        TextInput::make('name')->label('Nama')->required()->maxLength(255),
                        Textarea::make('address')->label('Alamat')->rows(3)->columnSpanFull(),
                    ]),
            ]);
    }
}
