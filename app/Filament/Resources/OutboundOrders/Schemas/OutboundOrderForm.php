<?php

namespace App\Filament\Resources\OutboundOrders\Schemas;

use App\Filament\Documents\DocumentFields;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** OUT: barang + qty pesanan; lokasi ambil ditentukan saat "Rilis ke picking". */
class OutboundOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Pesanan keluar')->columns(3)->schema([
                DocumentFields::warehouse(),
                TextInput::make('partner')->label('Pelanggan')->required()->maxLength(255),
                TextInput::make('reference')->label('No. pesanan')->maxLength(100),
                DocumentFields::notes(),
            ]),
            Section::make('Barang')->schema([
                Repeater::make('lines')->relationship()->hiddenLabel()->minItems(1)->defaultItems(1)->columns(2)
                    ->addActionLabel('Tambah barang')
                    ->schema([DocumentFields::item(), DocumentFields::qty()]),
            ]),
        ]);
    }
}
