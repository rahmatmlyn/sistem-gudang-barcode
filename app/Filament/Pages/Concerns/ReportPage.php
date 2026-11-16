<?php

namespace App\Filament\Pages\Concerns;

use App\Enums\UserRole;
use App\Models\Item;
use App\Reports\ReportExporter;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Laporan di panel: hanya Admin & Supervisor (K6), filter di $filter, export Excel/PDF sesuai filter (UAT-21). */
trait ReportPage
{
    public ?array $filter = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasRole(UserRole::Admin, UserRole::Supervisor);
    }

    /** @return array{0: string, 1: string, 2: list<string>, 3: list<list<string|int>>}|null [judul, keterangan, kolom, baris] */
    abstract protected function exportData(): ?array;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')->label('Export Excel')->icon(Heroicon::OutlinedTableCells)->color('gray')
                ->action(fn () => $this->download('xlsx')),
            Action::make('exportPdf')->label('Export PDF')->icon(Heroicon::OutlinedDocumentArrowDown)->color('gray')
                ->action(fn () => $this->download('pdf')),
        ];
    }

    private function download(string $format): ?StreamedResponse
    {
        $data = $this->exportData();
        if ($data === null) {
            Notification::make()->title('Pilih barang dulu')->warning()->send();

            return null;
        }
        [$title, $subtitle, $headings, $rows] = $data;
        $content = $format === 'xlsx' ? ReportExporter::xlsx($headings, $rows) : ReportExporter::pdf($title, $subtitle, $headings, $rows);
        $name = str($title)->slug().'-'.now()->format('Ymd-His').'.'.$format;

        return response()->streamDownload(fn () => print ($content), $name);
    }

    private static function itemSelect(): Select
    {
        return Select::make('item_id')->label('Barang')->searchable()->live()
            ->getSearchResultsUsing(fn (string $search) => Item::query()
                ->where(fn ($query) => $query->where('sku', 'ilike', "%{$search}%")->orWhere('name', 'ilike', "%{$search}%"))
                ->orderBy('sku')->limit(50)->get()->mapWithKeys(fn (Item $item) => [$item->id => "{$item->sku} — {$item->name}"])->all())
            ->getOptionLabelUsing(fn ($value) => ($item = Item::query()->find($value)) ? "{$item->sku} — {$item->name}" : null);
    }
}
