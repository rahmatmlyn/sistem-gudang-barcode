<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id', 'item_id', 'from_location_id', 'to_location_id',
        'qty_planned', 'qty_scanned', 'qty_packed', 'qty_system',
    ];

    protected $attributes = [
        'qty_planned' => 0,
        'qty_scanned' => 0,
        'qty_packed' => 0,
    ];

    protected function casts(): array
    {
        return [
            'qty_planned' => 'integer',
            'qty_scanned' => 'integer',
            'qty_packed' => 'integer',
            'qty_system' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
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

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
