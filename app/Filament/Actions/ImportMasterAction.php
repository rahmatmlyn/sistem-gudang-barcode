<?php

namespace App\Filament\Actions;

use App\Imports\ImportReport;
use App\Imports\RowImporter;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Tombol header "Import" untuk resource master. File diunggah ke disk local, diimport sinkron, lalu dihapus.
 * Bila ada baris gagal, laporan CSV disimpan di local:laporan-import/{uuid}.csv (rute laporan-import.unduh).
 */
final class ImportMasterAction
{
    private const CONTOH_GAGAL = 5;

    /**
     * @param  class-string<RowImporter>  $importer
     * @param  class-string<resource>  $resource
     */
    public static function make(string $name, string $label, string $importer, string $resource): Action
    {
        $columns = implode(',', app($importer)->columns());

        return Action::make($name)
            ->label($label)
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('gray')
            ->visible(fn () => $resource::canCreate())
            ->modalDescription("Baris pertama harus berisi kolom: {$columns}. Baris yang gagal dilaporkan per nomor baris; baris lain tetap masuk.")
            ->modalSubmitActionLabel('Import')
            ->schema([
                FileUpload::make('file')
                    ->label('File CSV atau Excel (.xlsx)')
                    ->disk('local')
                    ->directory('imports')
                    ->visibility('private')
                    ->rules(['extensions:csv,xlsx'])
                    ->maxSize(10240)
                    ->required(),
            ])
            ->action(function (array $data) use ($importer) {
                $disk = Storage::disk('local');
                try {
                    $report = app($importer)->import($disk->path($data['file']));
                } finally {
                    $disk->delete($data['file']);
                }

                self::notify($report);
            });
    }

    private static function notify(ImportReport $report): void
    {
        if ($report->jumlahGagal() === 0) {
            Notification::make()->title('Import selesai')->body($report->ringkasan())->success()->send();

            return;
        }

        $name = (string) Str::uuid();
        Storage::disk('local')->put("laporan-import/{$name}.csv", $report->toCsv());

        $examples = collect($report->gagal)
            ->take(self::CONTOH_GAGAL)
            ->map(fn (array $messages, int $row) => "Baris {$row}: ".implode('; ', $messages))
            ->implode("\n");

        Notification::make()
            ->title('Import selesai dengan baris gagal')
            ->body($report->ringkasan()."\n".$examples)
            ->warning()
            ->persistent()
            ->actions([
                Action::make('unduhLaporan')
                    ->label('Unduh laporan (CSV)')
                    ->url(route('laporan-import.unduh', $name))
                    ->openUrlInNewTab(),
            ])
            ->send();
    }
}
