<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Wrapper نازک روی پکیج sallar/jdf (کلاس استاتیک Jdf) تا در کل پروژه یکدست استفاده شود
 * و اگر بعداً پکیج تقویم عوض شد، فقط همین فایل تغییر کند.
 *
 * نصب: composer require sallar/jdf
 */
class Jalali
{
    /** [year, month, day] شمسی معادل یک Carbon/تاریخ میلادی (پیش‌فرض: اکنون). */
    public static function fromGregorian(?Carbon $date = null): array
    {
        $date ??= Carbon::now();

        [$jy, $jm, $jd] = \Jdf::gregorian_to_jalali(
            (int) $date->format('Y'),
            (int) $date->format('m'),
            (int) $date->format('d')
        );

        return [$jy, $jm, $jd];
    }

    public static function currentYear(): int
    {
        return self::fromGregorian()[0];
    }

    public static function currentMonth(): int
    {
        return self::fromGregorian()[1];
    }

    public static function currentDay(): int
    {
        return self::fromGregorian()[2];
    }

    /**
     * آیا امروز، از نظر پنجره‌ی ثبت اضافه‌کار یک ماه شمسی مشخص، در بازه‌ی مجاز است؟
     * قانون: از activation_day به بعد تا آخرین روز همان ماه شمسی.
     * قبل از activation_day => false. برای ماه‌های شمسی گذشته/آینده هم false برمی‌گرداند،
     * چون ثبت فقط برای دوره‌ی جاری باز است.
     */
    public static function isWithinActivationWindow(int $jalaliYear, int $jalaliMonth, int $activationDay): bool
    {
        [$currentYear, $currentMonth, $currentDay] = self::fromGregorian();

        if ($currentYear !== $jalaliYear || $currentMonth !== $jalaliMonth) {
            return false;
        }

        return $currentDay >= $activationDay;
    }

    /** برچسب نمایشی «سال/ماه» فارسی، مثلاً «1403/04». */
    public static function periodLabel(int $jalaliYear, int $jalaliMonth): string
    {
        return sprintf('%04d/%02d', $jalaliYear, $jalaliMonth);
    }

    /** [year, month] بعد از جابه‌جایی $delta ماه (منفی = عقب، مثبت = جلو) - سال شمسی همیشه ۱۲ ماهه است. */
    public static function shiftMonth(int $jalaliYear, int $jalaliMonth, int $delta): array
    {
        $zeroBased = ($jalaliYear * 12) + ($jalaliMonth - 1) + $delta;
        $year = intdiv($zeroBased, 12);
        $month = ($zeroBased % 12) + 1;

        if ($month <= 0) {
            $month += 12;
            $year -= 1;
        }

        return [$year, $month];
    }

    /** [[year, month], ...] برای N ماه گذشته تا ماه جاری (یا ماه مبنای داده‌شده)، ترتیب قدیم -> جدید. */
    public static function lastMonths(int $count, ?int $jalaliYear = null, ?int $jalaliMonth = null): array
    {
        [$year, $month] = $jalaliYear && $jalaliMonth ? [$jalaliYear, $jalaliMonth] : self::fromGregorian();

        $months = [];
        for ($i = $count - 1; $i >= 0; $i--) {
            $months[] = self::shiftMonth($year, $month, -$i);
        }

        return $months;
    }
}
