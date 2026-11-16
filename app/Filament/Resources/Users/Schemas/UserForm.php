<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Pengguna')
                    ->columns(2)
                    ->schema([
                        TextInput::make('username')->label('Username')->required()->maxLength(50)
                            ->alphaDash()->unique(ignoreRecord: true),
                        TextInput::make('name')->label('Nama')->required()->maxLength(255),
                        TextInput::make('email')->label('Email')->email()->maxLength(255)->unique(ignoreRecord: true),
                        Select::make('role')->label('Peran')->options(UserRole::class)->required()
                            ->default(UserRole::Staf->value)
                            ->helperText('Staf Gudang hanya bisa membuka halaman /scan.'),
                        TextInput::make('password')->label('Password')->password()->revealable()
                            ->minLength(8)->maxLength(255)
                            ->required(fn (string $operation) => $operation === 'create')
                            ->dehydrated(fn (?string $state) => filled($state))
                            ->helperText(fn (string $operation) => $operation === 'edit' ? 'Kosongkan bila tidak diganti.' : null),
                    ]),
            ]);
    }
}
