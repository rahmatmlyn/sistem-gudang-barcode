<?php

namespace App\Filament\Pages;

use App\Enums\LocationType;
use App\Filament\Pages\Concerns\ReportPage;
use App\Models\Location;
use App\Reports\StokPerLokasi as StokPerLokasiReport;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

class StokPerLokasi extends Page implements HasTable
{
    use InteractsWithTable;
    use ReportPage;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Stok per Lokasi';

    protected string $view = 'filament.pages.stok-per-lokasi';

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        $locations = fn (array $types = []) => Location::query()->when($types, fn ($query) => $query->whereIn('type', $types))
            ->orderBy('code')->pluck('code', 'id')->all();

        return $schema->statePath('filter')->columns(3)->components([
            Select::make('zona_id')->label('Zona / rak')->searchable()->live()
                ->options(fn () => $locations([LocationType::Zona->value, LocationType::Rak->value])),
            Select::make('location_id')->label('Lokasi')->searchable()->live()->options(fn () => $locations()),
            self::itemSelect(),
        ]);
    }

    public function report(): StokPerLokasiReport
    {
        $id = fn (string $key) => filled($this->filter[$key] ?? null) ? (int) $this->filter[$key] : null;

        return new StokPerLokasiReport($id('zona_id'), $id('location_id'), $id('item_id'));
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->report()->query())
            ->paginated([25, 50, 100])
            ->columns([
                TextColumn::make('location_code')->label('Lokasi'),
                TextColumn::make('sku')->label('SKU'),
                TextColumn::make('item_name')->label('Barang')->wrap(),
                TextColumn::make('base_unit')->label('Satuan'),
                TextColumn::make('qty')->label('Saldo')->numeric(),
                TextColumn::make('dipesan')->label('Dipesan')->numeric(),
                TextColumn::make('tersedia')->label('Tersedia')->numeric()->weight('bold')
                    ->state(fn ($record) => (int) $record->qty - (int) $record->dipesan),
            ]);
    }

    protected function exportData(): ?array
    {
        $report = $this->report();

        return ['Stok per Lokasi', $report->filterDescription(), $report->headings(), $report->exportRows()];
    }
}
