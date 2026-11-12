<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'number', 'type', 'status', 'warehouse_id', 'reference', 'partner', 'notes',
        'related_document_id', 'reversal_of_id', 'created_by',
        'submitted_at', 'completed_at', 'completed_by', 'approved_by', 'approved_at', 'approval_reason',
    ];

    protected $attributes = [
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'status' => DocumentStatus::class,
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DocumentLine::class);
    }

    public function opnameLocations(): HasMany
    {
        return $this->hasMany(OpnameLocation::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function relatedDocument(): BelongsTo
    {
        return $this->belongsTo(self::class, 'related_document_id');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', DocumentStatus::openValues());
    }

    public function scopeOfType(Builder $query, DocumentType $type): void
    {
        $query->where('type', $type);
    }
}
