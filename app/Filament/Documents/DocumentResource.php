<?php

namespace App\Filament\Documents;

use App\Enums\DocumentType;
use App\Models\Document;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Dasar resource dokumen: satu model Document, di-scope per tipe. */
abstract class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static string|UnitEnum|null $navigationGroup = 'Dokumen';

    protected static ?string $recordTitleAttribute = 'number';

    abstract public static function documentType(): DocumentType;

    public static function getModelLabel(): string
    {
        return strtolower(static::documentType()->getLabel());
    }

    public static function getPluralModelLabel(): string
    {
        return static::getModelLabel();
    }

    public static function getNavigationLabel(): string
    {
        return static::documentType()->getLabel().' ('.static::documentType()->value.')';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', static::documentType());
    }

    public static function table(Table $table): Table
    {
        return DocumentTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DocumentInfolist::configure($schema);
    }

    public static function getRelations(): array
    {
        return [LinesRelationManager::class];
    }
}
