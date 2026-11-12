<?php

namespace App\Models;

use App\Enums\LocationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
