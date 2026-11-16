<?php

namespace App\Filament\Resources\Locations\Schemas;

use App\Enums\LocationType;
use App\Models\Location;
use App\Models\Warehouse;
use App\Rules\BarcodeUnik;
use App\Rules\ParentLokasiValid;
use App\Support\BarcodeRegistry;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class LocationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Lokasi')
                    ->columns(2)
                    ->schema([
                        Select::make('warehouse_id')->label('Gudang')->required()->live()
                            ->options(fn () => Warehouse::query()->orderBy('code')->pluck('name', 'id')->all())
                            ->default(fn () => Warehouse::query()->count() === 1 ? Warehouse::query()->value('id') : null),
                        Select::make('type')->label('Tipe')->options(LocationType::class)->required(),
                        TextInput::make('code')->label('Kode')->required()->maxLength(50)
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('warehouse_id', $get('warehouse_id')))
                            ->rule(fn (Get $get, ?Location $record) => self::defaultBarcodeFree($get, $record)),
                        TextInput::make('name')->label('Nama')->maxLength(100),
                        Select::make('parent_id')->label('Lokasi induk')->searchable()
                            ->helperText('Kosongkan untuk lokasi teratas (zona, area terima/kirim).')
                            ->options(fn (Get $get, ?Location $record) => Location::parentOptions($get('warehouse_id'), $record))
                            ->rule(fn (Get $get, ?Location $record) => new ParentLokasiValid($record, $get('warehouse_id'))),
                        TextInput::make('barcode')->label('Barcode')->maxLength(100)
                            ->placeholder('Otomatis: LOC-{kode}')
                            ->rule(fn (?Location $record) => new BarcodeUnik($record)),
                        Toggle::make('is_active')->label('Aktif')->default(true)->inline(false),
                    ]),
            ]);
    }

    /** Barcode kosong akan diisi "LOC-{kode}" oleh model; pastikan kode itu belum dipakai. */
    private static function defaultBarcodeFree(Get $get, ?Location $record): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($get, $record) {
            if (blank($get('barcode')) && BarcodeRegistry::isTaken('LOC-'.$value, $record)) {
                $fail("Barcode otomatis LOC-{$value} sudah dipakai; isi barcode secara manual.");
            }
        };
    }
}
