<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use HasFactory;

    protected $fillable = ['sku', 'name', 'category', 'base_unit', 'min_stock', 'photo_path', 'is_active'];

    protected function casts(): array
    {
        return [
            'min_stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(ItemBarcode::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** Sudah dipakai dokumen, ledger, atau saldo: tidak boleh dihapus (nonaktifkan saja). */
    public function isReferenced(): bool
    {
        return $this->movements()->exists()
            || $this->balances()->exists()
            || DocumentLine::query()->where('item_id', $this->id)->exists();
    }
}
