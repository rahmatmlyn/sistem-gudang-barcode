<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('username')
            ->columns([
                TextColumn::make('username')->label('Username')->searchable()->sortable(),
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('role')->label('Peran')->badge()->sortable(),
                TextColumn::make('email')->label('Email')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('role')->label('Peran')->options(UserRole::class),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
