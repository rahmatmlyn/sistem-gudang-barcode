<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\ReportPage;
use App\Models\Location;
use App\Models\StockMovement;
use App\Reports\KartuStok as KartuStokReport;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use UnitEnum;

class KartuStok extends Page implements HasTable
{
    use InteractsWithTable;
    use ReportPage;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Kartu Stok';

    protected string $view = 'filament.pages.kartu-stok';

    public function mount(): void
    {
        $this->form->fill(['dari' => now()->startOfMonth()->toDateString(), 'sampai' => now()->toDateString()]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('filter')->columns(4)->components([
            self::itemSelect()->required(),
            Select::make('location_id')->label('Lokasi')->searchable()->live()
                ->options(fn () => Location::query()->orderBy('code')->pluck('code', 'id')->all()),
            DatePicker::make('dari')->label('Dari')->required()->live(),
            DatePicker::make('sampai')->label('Sampai')->required()->live(),
        ]);
    }

    public function report(): ?KartuStokReport
    {
        $filter = $this->filter ?? [];
        if (blank($filter['item_id'] ?? null) || blank($filter['dari'] ?? null) || blank($filter['sampai'] ?? null)) {
            return null;
        }

        return new KartuStokReport((int) $filter['item_id'], filled($filter['location_id'] ?? null) ? (int) $filter['location_id'] : null,
            Carbon::parse($filter['dari']), Carbon::parse($filter['sampai']));
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->report()?->query() ?? StockMovement::query()->whereRaw('1 = 0'))
            ->paginated([25, 50, 100])
            ->emptyStateHeading('Pilih barang dan rentang tanggal')
            ->columns([
                TextColumn::make('created_at')->label('Tanggal')->dateTime('d/m/Y H:i'),
                TextColumn::make('documentLine.document.number')->label('Dokumen')->placeholder('-'),
                TextColumn::make('type')->label('Tipe')->badge(),
                TextColumn::make('fromLocation.code')->label('Dari')->placeholder('-'),
                TextColumn::make('toLocation.code')->label('Ke')->placeholder('-'),
                TextColumn::make('masuk')->label('Masuk')->numeric(),
                TextColumn::make('keluar')->label('Keluar')->numeric(),
                TextColumn::make('saldo')->label('Saldo')->numeric()->weight('bold'),
            ]);
    }

    protected function exportData(): ?array
    {
        $report = $this->report();

        return $report === null ? null : ['Kartu Stok', $report->description(), $report->headings(), $report->exportRows()];
    }
}
