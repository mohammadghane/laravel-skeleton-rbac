<?php

namespace App\Services;

use App\Models\Location;

/**
 * بررسی اینکه یک نقطه داخل شعاع مجازِ کدام موقعیت است.
 *
 * منطق در یک سرویس جداست تا هم کنترلر ثبت نظر و هم بررسی زنده‌ی سمت کلاینت
 * (که همین endpoint را صدا می‌زند) از یک مرجع واحد استفاده کنند - وگرنه دو
 * پیاده‌سازی موازی می‌شد که می‌توانند از هم جدا بیفتند.
 */
class LocationProximityService
{
    /**
     * نزدیک‌ترین موقعیتی که این نقطه را پوشش می‌دهد.
     *
     * «نزدیک‌ترین» مهم است نه «اولین»: وقتی شعاع‌ها هم‌پوشانی دارند، نتیجه باید
     * قطعی و قابل توضیح باشد.
     *
     * @return array{allowed:bool, location:?Location, distance:?int}
     */
    public function check(float $latitude, float $longitude): array
    {
        $best = null;
        $bestDistance = null;

        // فقط موقعیت‌های فعال: غیرفعال‌کردن یک موقعیت باید دسترسی را ببندد.
        foreach (Location::where('is_active', true)->get() as $location) {
            $distance = $location->distanceTo($latitude, $longitude);

            if ($distance > $location->allowed_radius) {
                continue;
            }

            if ($bestDistance === null || $distance < $bestDistance) {
                $best = $location;
                $bestDistance = $distance;
            }
        }

        return [
            'allowed' => $best !== null,
            'location' => $best,
            'distance' => $bestDistance === null ? null : (int) round($bestDistance),
        ];
    }

    /** نزدیک‌ترین موقعیت به این نقطه، صرف‌نظر از شعاع - برای پیام راهنما. */
    public function nearest(float $latitude, float $longitude): ?array
    {
        $best = null;
        $bestDistance = null;

        foreach (Location::where('is_active', true)->get() as $location) {
            $distance = $location->distanceTo($latitude, $longitude);

            if ($bestDistance === null || $distance < $bestDistance) {
                $best = $location;
                $bestDistance = $distance;
            }
        }

        return $best === null ? null : [
            'location' => $best,
            'distance' => (int) round($bestDistance),
        ];
    }
}
