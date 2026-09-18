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
        'title', 'latitude', 'longitude', 'allowed_radius', 'description', 'is_active', 'created_by',
    ];

    /** شعاع زمین بر حسب متر - مبنای محاسبه‌ی فاصله. */
    private const EARTH_RADIUS_METERS = 6_371_000;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            // decimal نگه داشته می‌شود تا رقم‌های اعشار در رفت‌وبرگشت گم نشوند؛
            // cast به float مقدار ذخیره‌شده را گرد می‌کرد.
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'allowed_radius' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * فاصله‌ی این موقعیت تا یک نقطه، بر حسب متر (فرمول Haversine).
     *
     * چرا Haversine و نه فاصله‌ی اقلیدسی روی درجه: یک درجه طول جغرافیایی در
     * استوا ~۱۱۱ کیلومتر است ولی در عرض‌های بالاتر به‌شدت کوچک‌تر می‌شود. محاسبه‌ی
     * ساده‌ی sqrt(dlat² + dlng²) در ایران چند ده درصد خطا می‌داد و شعاع‌های
     * کوچک (مثلاً ۵۰ متر) را بی‌معنا می‌کرد.
     */
    public function distanceTo(float $latitude, float $longitude): float
    {
        $lat1 = deg2rad((float) $this->latitude);
        $lat2 = deg2rad($latitude);
        $deltaLat = $lat2 - $lat1;
        $deltaLng = deg2rad($longitude - (float) $this->longitude);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1) * cos($lat2) * sin($deltaLng / 2) ** 2;

        return self::EARTH_RADIUS_METERS * 2 * asin(min(1.0, sqrt($a)));
    }

    /** آیا این نقطه داخل شعاع مجاز این موقعیت است؟ */
    public function covers(float $latitude, float $longitude): bool
    {
        return $this->distanceTo($latitude, $longitude) <= $this->allowed_radius;
    }
}
