<?php

namespace App\Listeners;

use App\Enums\UserRole;
use App\Events\StokBerubah;
use App\Models\Item;
use App\Models\StockBalance;
use App\Models\User;
use App\Notifications\StokMinimumNotification;
use Throwable;

/**
 * Kirim notifikasi sekali saat stok total barang melintasi batas minimum ke bawah (stok ≤ minimum).
 * items.min_stock_alerted_at menandai notifikasi sudah terkirim; dikosongkan saat stok kembali di atas minimum.
 * Update bersyarat (whereNull) membuat dua penyelesaian bersamaan tidak mengirim dua kali.
 */
class KirimNotifikasiStokMinimum
{
    public function handle(StokBerubah $event): void
    {
        $items = Item::query()->whereIn('id', $event->itemIds)->where('min_stock', '>', 0)->get();

        foreach ($items as $item) {
            $qty = (int) StockBalance::query()->where('item_id', $item->id)->sum('qty');

            if ($qty > $item->min_stock) {
                Item::query()->whereKey($item->id)->whereNotNull('min_stock_alerted_at')->update(['min_stock_alerted_at' => null]);

                continue;
            }

            $claimed = Item::query()->whereKey($item->id)->whereNull('min_stock_alerted_at')->update(['min_stock_alerted_at' => now()]);
            if ($claimed === 1) {
                $this->notifyAdmins(new StokMinimumNotification($item, $qty));
            }
        }
    }

    /** Listener berjalan setelah dokumen tersimpan: email yang gagal dicatat, tidak boleh menggagalkan aksi pengguna. */
    private function notifyAdmins(StokMinimumNotification $notification): void
    {
        foreach (User::query()->where('role', UserRole::Admin)->get() as $admin) {
            try {
                $admin->notify($notification);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}
