<?php

namespace App\Filament\Documents;

use App\Enums\DocumentType;
use App\Enums\OpnameLocationStatus;
use App\Exceptions\StockRuleException;
use App\Filament\Resources\Adjustments\AdjustmentResource;
use App\Models\Document;
use App\Services\DocumentService;
use App\Services\OpnameService;
use App\Services\PickingAllocator;
use App\Services\StockService;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

/**
 * Aksi siklus hidup dokumen di halaman lihat. Penolakan aturan bisnis tampil sebagai notifikasi, bukan error 500.
 * Semua perubahan stok lewat StockService / OpnameService / PickingAllocator.
 */
final class DocumentActions
{
    public static function rilis(): Action
    {
        return Action::make('rilis')->label('Rilis ke picking')->icon(Heroicon::OutlinedQueueList)
            ->requiresConfirmation()
            ->modalDescription('Baris pesanan dipecah per lokasi rak sesuai stok tersedia, urut kode lokasi.')
            ->visible(fn (Document $record) => $record->type === DocumentType::OUT && self::can('update', $record))
            ->action(fn (Document $record) => self::run('Rilis gagal', function () use ($record) {
                app(PickingAllocator::class)->allocate($record, auth()->user());
                Notification::make()->title('Pesanan dirilis ke picking')->success()->send();
            }));
    }

    public static function batalkan(): Action
    {
        return Action::make('batalkan')->label('Batalkan')->icon(Heroicon::OutlinedXCircle)->color('danger')
            ->modalDescription('Dokumen dibatalkan; qty yang sudah di-scan tidak lagi mencadangkan stok.')
            ->schema([self::reason()])
            ->visible(fn (Document $record) => self::can('cancel', $record))
            ->action(fn (Document $record, array $data) => self::run('Pembatalan gagal', function () use ($record, $data) {
                app(DocumentService::class)->cancel($record, auth()->user(), $data['alasan']);
                Notification::make()->title('Dokumen dibatalkan')->success()->send();
            }));
    }

    public static function balik(): Action
    {
        return Action::make('balik')->label('Buat transaksi balik')->icon(Heroicon::OutlinedArrowUturnLeft)->color('warning')
            ->modalDescription('Membuat dokumen ADJ yang membalik semua mutasi stok dokumen ini. Mutasi lama tidak diubah.')
            ->schema([self::reason()])
            ->visible(fn (Document $record) => self::can('reverse', $record))
            ->action(function (Document $record, array $data, Action $action) {
                self::run('Transaksi balik gagal', function () use ($record, $data, $action) {
                    $reversal = app(StockService::class)->reverse($record, auth()->user(), $data['alasan']);
                    Notification::make()->title("Transaksi balik {$reversal->number} dibuat")->success()->send();
                    $action->redirect(AdjustmentResource::getUrl('view', ['record' => $reversal]));
                });
            });
    }

    public static function ajukan(): Action
    {
        return Action::make('ajukan')->label('Ajukan approval')->icon(Heroicon::OutlinedPaperAirplane)
            ->requiresConfirmation()
            ->visible(fn (Document $record) => $record->type === DocumentType::OPN && $record->status->isOpen()
                && $record->submitted_at === null && self::can('view', $record)
                && $record->opnameLocations()->exists()
                && ! $record->opnameLocations()->where('status', OpnameLocationStatus::Belum)->exists())
            ->action(fn (Document $record) => self::run('Pengajuan gagal', function () use ($record) {
                app(OpnameService::class)->submit($record, auth()->user());
                Notification::make()->title('Opname diajukan untuk approval')->success()->send();
            }));
    }

    public static function setujui(): Action
    {
        return Action::make('setujui')->label('Setujui selisih')->icon(Heroicon::OutlinedCheckCircle)->color('success')
            ->modalDescription('Selisih fisik vs sistem menjadi dokumen penyesuaian (ADJ) atas nama Anda.')
            ->schema([self::reason()])
            ->visible(fn (Document $record) => self::can('approve', $record))
            ->action(fn (Document $record, array $data) => self::run('Approval gagal', function () use ($record, $data) {
                $adjustment = app(OpnameService::class)->approve($record, auth()->user(), $data['alasan']);
                Notification::make()->success()
                    ->title($adjustment ? "Selisih disetujui, {$adjustment->number} dibuat" : 'Disetujui tanpa selisih')->send();
            }));
    }

    public static function tolak(): Action
    {
        return Action::make('tolak')->label('Tolak')->icon(Heroicon::OutlinedNoSymbol)->color('danger')
            ->modalDescription('Opname dibatalkan tanpa mengubah stok.')
            ->schema([self::reason()])
            ->visible(fn (Document $record) => self::can('approve', $record))
            ->action(fn (Document $record, array $data) => self::run('Penolakan gagal', function () use ($record, $data) {
                app(OpnameService::class)->reject($record, auth()->user(), $data['alasan']);
                Notification::make()->title('Opname ditolak')->success()->send();
            }));
    }

    private static function reason(): Textarea
    {
        return Textarea::make('alasan')->label('Alasan')->required()->rows(3);
    }

    private static function can(string $ability, Document $record): bool
    {
        return (bool) auth()->user()?->can($ability, $record);
    }

    private static function run(string $failureTitle, Closure $callback): void
    {
        try {
            $callback();
        } catch (StockRuleException|InvalidArgumentException|AuthorizationException $exception) {
            Notification::make()->title($failureTitle)->body($exception->getMessage())->danger()->persistent()->send();
        }
    }
}
