<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LocationComment extends Model
{
    use Auditable;
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'latitude', 'longitude', 'body',
        'was_allowed', 'location_id', 'distance_meters',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'was_allowed' => 'boolean',
            'distance_meters' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** موقعیتی که در لحظه‌ی ثبت، نظر را مجاز کرده بود (اگر مجاز بوده). */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
