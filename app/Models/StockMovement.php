<?php

namespace App\Models;

use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Ledger stok: hanya ditulis oleh StockService, tidak pernah diubah atau dihapus (aturan 3).
 */
class StockMovement extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'item_id', 'from_location_id', 'to_location_id', 'qty', 'type', 'document_line_id', 'user_id', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'qty' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        $tolak = fn () => throw new LogicException('stock_movements tidak boleh diubah atau dihapus; buat transaksi balik.');

        static::updating($tolak);
        static::deleting($tolak);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function documentLine(): BelongsTo
    {
        return $this->belongsTo(DocumentLine::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
