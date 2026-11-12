<?php

namespace App\Models;

use App\Enums\ScanMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'client_scan_id', 'user_id', 'document_id', 'mode', 'barcode', 'location_id', 'success', 'code', 'message', 'response',
    ];

    protected function casts(): array
    {
        return [
            'mode' => ScanMode::class,
            'success' => 'boolean',
            'response' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
