<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Saldo terkini per item–lokasi; hanya ditulis oleh StockService dalam transaksi yang sama dengan movement-nya.
 */
class StockBalance extends Model
{
    protected $fillable = ['item_id', 'location_id', 'qty'];

    protected function casts(): array
    {
        return ['qty' => 'integer'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
