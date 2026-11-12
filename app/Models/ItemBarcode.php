<?php

namespace App\Models;

use App\Enums\BarcodeType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemBarcode extends Model
{
    use HasFactory;

    protected $fillable = ['item_id', 'barcode', 'type', 'unit', 'factor'];

    protected function casts(): array
    {
        return [
            'type' => BarcodeType::class,
            'factor' => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** Aturan 4: qty kemasan → qty satuan dasar. */
    public function toBaseQty(int $qty): int
    {
        return $qty * $this->factor;
    }
}
