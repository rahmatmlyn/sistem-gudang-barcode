<?php

namespace App\Notifications;

use App\Filament\Resources\Items\ItemResource;
use App\Models\Item;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** FR-16 / UAT-22: stok total barang mencapai atau di bawah stok minimum (dalam aplikasi + email). */
class StokMinimumNotification extends Notification
{
    public function __construct(public readonly Item $item, public readonly int $qty) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Stok minimum: {$this->item->sku} {$this->item->name}")
            ->line("Stok {$this->item->name} ({$this->item->sku}) tinggal {$this->qty} {$this->item->base_unit}, minimum {$this->item->min_stock}.")
            ->action('Lihat barang', ItemResource::getUrl('edit', ['record' => $this->item], panel: 'admin'));
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title("Stok minimum: {$this->item->name}")
            ->body("{$this->item->sku}: stok {$this->qty} {$this->item->base_unit}, minimum {$this->item->min_stock}.")
            ->warning()
            ->actions([Action::make('lihat')->label('Lihat barang')->url(ItemResource::getUrl('edit', ['record' => $this->item], panel: 'admin'))])
            ->getDatabaseMessage();
    }
}
