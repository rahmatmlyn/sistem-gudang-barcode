<?php

namespace App\Filament\Resources\Transfers\Schemas;

use App\Filament\Documents\DocumentFields;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** TRF: pindah barang antar lokasi dalam satu gudang (FR-11). */
class TransferForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Transfer')->columns(2)->schema([
                DocumentFields::warehouse(),
                DocumentFields::notes(),
            ]),
            Section::make('Barang')->schema([
                Repeater::make('lines')->relationship()->hiddenLabel()->minItems(1)->defaultItems(1)->columns(4)
                    ->addActionLabel('Tambah barang')
                    ->schema([
                        DocumentFields::item()->distinct(false),
                        DocumentFields::location('from_location_id', 'fromLocation', 'Lokasi asal'),
                        DocumentFields::location('to_location_id', 'toLocation', 'Lokasi tujuan')->different('from_location_id'),
                        DocumentFields::qty(),
                    ]),
            ]),
        ]);
    }
}
