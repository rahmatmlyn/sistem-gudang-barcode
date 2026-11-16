<?php

namespace App\Filament\Resources\GoodsReceipts\Schemas;

use App\Filament\Documents\DocumentFields;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** GRN: rencana penerimaan (opsional); barang diterima ke area terima lewat scan. */
class GoodsReceiptForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Penerimaan')->columns(3)->schema([
                DocumentFields::warehouse(),
                TextInput::make('partner')->label('Supplier')->maxLength(255),
                TextInput::make('reference')->label('No. PO / surat jalan supplier')->maxLength(100),
                DocumentFields::notes(),
            ]),
            Section::make('Rencana barang')
                ->description('Boleh dikosongkan: barang yang di-scan saat penerimaan otomatis masuk dokumen.')
                ->schema([
                    Repeater::make('lines')->relationship()->hiddenLabel()->defaultItems(0)->columns(2)
                        ->addActionLabel('Tambah barang')
                        ->schema([DocumentFields::item(), DocumentFields::qty('Qty rencana (satuan dasar)', 0)]),
                ]),
        ]);
    }
}
