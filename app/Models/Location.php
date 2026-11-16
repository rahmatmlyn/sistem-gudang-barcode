<?php

namespace App\Models;

use App\Enums\LocationType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Location extends Model
{
    use HasFactory;

    protected $fillable = ['warehouse_id', 'parent_id', 'code', 'name', 'type', 'barcode', 'is_active'];

    protected function casts(): array
    {
        return [
            'type' => LocationType::class,
            'is_active' => 'boolean',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('code');
    }

    public function balances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }

    protected static function booted(): void
    {
        static::saving(function (Location $location) {
            if (blank($location->barcode)) {
                $location->barcode = 'LOC-'.$location->code;
            }
        });
    }

    /** "A › A-01 › A-01-02". Muat relasi parent.parent... lebih dulu untuk menghindari N+1. */
    protected function path(): Attribute
    {
        return Attribute::get(function () {
            $codes = [];
            for ($node = $this; $node !== null && count($codes) < 20; $node = $node->parent) {
                array_unshift($codes, $node->code);
            }

            return implode(' › ', $codes);
        });
    }

    /** @return list<int> semua turunan (anak, cucu, ...) lokasi ini */
    public function descendantIds(): array
    {
        if (! $this->exists) {
            return [];
        }

        $rows = DB::select(
            'WITH RECURSIVE turunan AS (
                SELECT id FROM locations WHERE parent_id = ?
                UNION SELECT l.id FROM locations l JOIN turunan t ON l.parent_id = t.id
            ) SELECT id FROM turunan',
            [$this->id],
        );

        return array_map(fn ($row) => (int) $row->id, $rows);
    }

    /**
     * Pilihan induk untuk form: lokasi di gudang yang sama, kecuali dirinya dan turunannya.
     *
     * @return array<int, string>
     */
    public static function parentOptions(int|string|null $warehouseId, ?self $except = null): array
    {
        if (blank($warehouseId)) {
            return [];
        }

        $excluded = $except?->exists ? [$except->id, ...$except->descendantIds()] : [];

        return static::query()
            ->where('warehouse_id', $warehouseId)
            ->whereNotIn('id', $excluded)
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->mapWithKeys(fn (self $location) => [$location->id => trim($location->code.' '.$location->name)])
            ->all();
    }

    /** Sudah dipakai dokumen, ledger, saldo, atau punya anak: tidak boleh dihapus. */
    public function isReferenced(): bool
    {
        return $this->children()->exists()
            || StockBalance::query()->where('location_id', $this->id)->exists()
            || StockMovement::query()->where('from_location_id', $this->id)->orWhere('to_location_id', $this->id)->exists()
            || DocumentLine::query()->where('from_location_id', $this->id)->orWhere('to_location_id', $this->id)->exists()
            || OpnameLocation::query()->where('location_id', $this->id)->exists();
    }
}
