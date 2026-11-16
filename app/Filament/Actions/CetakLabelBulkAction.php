<?php

namespace App\Filament\Actions;

use App\Enums\UserRole;
use App\Exceptions\LabelTidakValidException;
use Closure;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Aksi massal "Cetak label" (Admin): PDF 50 × 30 mm lewat LabelService. */
final class CetakLabelBulkAction
{
    /** @param Closure(Collection, int): string $pdf membuat isi PDF dari record terpilih dan jumlah salinan */
    public static function make(string $fileName, Closure $pdf): BulkAction
    {
        return BulkAction::make('cetakLabel')
            ->label('Cetak label')
            ->icon(Heroicon::OutlinedPrinter)
            ->visible(fn () => (bool) auth()->user()?->hasRole(UserRole::Admin))
            ->modalDescription('Label PDF 50 × 30 mm, satu label per halaman.')
            ->schema([
                TextInput::make('jumlah')->label('Jumlah salinan per label')->integer()->minValue(1)->maxValue(100)->default(1)->required(),
            ])
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, array $data) use ($fileName, $pdf): ?StreamedResponse {
                try {
                    $content = $pdf($records, (int) $data['jumlah']);
                } catch (LabelTidakValidException $exception) {
                    Notification::make()->title('Label tidak bisa dicetak')->body($exception->getMessage())->danger()->persistent()->send();

                    return null;
                }

                return response()->streamDownload(fn () => print ($content), $fileName.'-'.now()->format('Ymd-His').'.pdf');
            });
    }
}
