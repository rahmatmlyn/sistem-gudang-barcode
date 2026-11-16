<?php

namespace App\Filament\Resources\Items\Schemas;

use App\Enums\BarcodeType;
use App\Rules\BarcodeUnik;
use App\Rules\Ean13Valid;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class ItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Data barang')
                    ->columns(2)
                    ->schema([
                        TextInput::make('sku')->label('SKU')->required()->maxLength(50)->unique(ignoreRecord: true),
                        TextInput::make('name')->label('Nama')->required()->maxLength(255),
                        TextInput::make('category')->label('Kategori')->maxLength(100),
                        TextInput::make('base_unit')->label('Satuan dasar')->required()->maxLength(30)
                            ->helperText('Semua stok dihitung dalam satuan ini.'),
                        TextInput::make('min_stock')->label('Stok minimum')->integer()->minValue(0)->default(0)->required(),
                        Toggle::make('is_active')->label('Aktif')->default(true)->inline(false),
                        FileUpload::make('photo_path')->label('Foto')->image()->disk('public')->directory('barang')
                            ->maxSize(2048)->columnSpanFull(),
                    ]),
                Section::make('Barcode')
                    ->description('Satu barang bisa punya beberapa barcode, mis. pcs dan dus. Faktor = isi kemasan dalam satuan dasar.')
                    ->schema([
                        Repeater::make('barcodes')
                            ->relationship()
                            ->hiddenLabel()
                            ->defaultItems(0)
                            ->columns(4)
                            ->addActionLabel('Tambah barcode')
                            ->schema(self::barcodeFields()),
                    ]),
            ]);
    }

    /** @return list<\Filament\Schemas\Components\Component|\Filament\Forms\Components\Field> */
    private static function barcodeFields(): array
    {
        return [
            TextInput::make('barcode')->label('Barcode')->required()->maxLength(100)->distinct()
                ->rule(fn (?Model $record) => new BarcodeUnik($record))
                ->rule(fn (Get $get) => new Ean13Valid($get('type'))),
            Select::make('type')->label('Jenis')->options(BarcodeType::class)->required()->default(BarcodeType::Ean13->value),
            TextInput::make('unit')->label('Satuan kemasan')->required()->maxLength(30)->default('pcs'),
            TextInput::make('factor')->label('Faktor konversi')->integer()->minValue(1)->default(1)->required(),
        ];
    }
}
