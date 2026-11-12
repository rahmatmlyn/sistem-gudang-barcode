<?php

namespace App\Models;

use App\Enums\OpnameLocationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpnameLocation extends Model
{
    protected $fillable = ['document_id', 'location_id', 'status', 'counted_by', 'counted_at'];

    protected $attributes = [
        'status' => 'belum',
    ];

    protected function casts(): array
    {
        return [
            'status' => OpnameLocationStatus::class,
            'counted_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function counter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }
}
