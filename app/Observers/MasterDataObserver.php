<?php

namespace App\Observers;

use App\Models\Item;
use App\Models\ItemBarcode;
use App\Models\Location;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Mencatat perubahan master data ke activity_logs (UAT-24) dengan aksi "<subjek>.dibuat|diubah|dihapus".
 * Atribut hidden (password, remember_token) dan timestamp tidak pernah dicatat.
 */
class MasterDataObserver
{
    /** model → nama subjek di kolom action. Tambah model di sini → daftarkan juga di AppServiceProvider. */
    public const SUBJECTS = [
        Item::class => 'barang',
        ItemBarcode::class => 'barcode',
        Location::class => 'lokasi',
        Warehouse::class => 'gudang',
        User::class => 'pengguna',
    ];

    public function __construct(private ActivityLogger $logger) {}

    public function created(Model $model): void
    {
        $this->log($model, 'dibuat', null, $this->visible($model, $model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        $after = $this->visible($model, $model->getChanges());
        if ($after === []) {
            return;
        }

        $before = [];
        foreach (array_keys($after) as $key) {
            $before[$key] = $model->getRawOriginal($key);
        }

        $this->log($model, 'diubah', $before, $after);
    }

    public function deleted(Model $model): void
    {
        $this->log($model, 'dihapus', $this->visible($model, $model->getAttributes()), null);
    }

    private function log(Model $model, string $verb, ?array $before, ?array $after): void
    {
        $user = auth()->user();

        $this->logger->log(
            $user instanceof User ? $user : null,
            self::SUBJECTS[$model::class].'.'.$verb,
            $model,
            $before,
            $after,
        );
    }

    private function visible(Model $model, array $attributes): array
    {
        return Arr::except($attributes, [...$model->getHidden(), $model->getCreatedAtColumn(), $model->getUpdatedAtColumn()]);
    }
}
