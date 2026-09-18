<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * یک نقطه‌ی مشخص روی نقشه که کاربر ثبت کرده است.
 */
class Location extends Model
{
    use Auditable;
    use SoftDeletes;

    protected $fillable = [
        'title', 'latitude', 'longitude', 'description', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            // decimal نگه داشته می‌شود تا رقم‌های اعشار در رفت‌وبرگشت گم نشوند؛
            // cast به float مقدار ذخیره‌شده را گرد می‌کرد.
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
