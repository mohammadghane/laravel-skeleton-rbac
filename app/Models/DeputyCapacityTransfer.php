<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Support\Jalali;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * واگذاری ظرفیت اضافه‌کار مازاد از یک معاونت به معاونت دیگر، در یک ماه شمسی مشخص
 * (تبصره‌ی اول بخش «پارامترهای وابسته به سمت سازمانی» - بازنگری بند ۴.۲).
 *
 * محاسبه‌ی اثر آن در OvertimeCeilingService انجام می‌شود:
 *   ظرفیت مؤثر = سقف پایه - مجموع واگذارشده + مجموع دریافت‌شده
 */
class DeputyCapacityTransfer extends Model
{
    use Auditable;

    protected $fillable = [
        'from_deputy_id', 'to_deputy_id',
        'jalali_year', 'jalali_month',
        'hours', 'note', 'created_by',
    ];

    protected $casts = [
        'hours' => 'decimal:2',
    ];

    public function auditLabel(): string
    {
        return __('واگذاری ظرفیت مازاد') . ': '
            . ($this->fromDeputy?->title ?? '?') . ' → ' . ($this->toDeputy?->title ?? '?')
            . ' - ' . Jalali::periodLabel($this->jalali_year, $this->jalali_month);
    }

    public function fromDeputy(): BelongsTo
    {
        return $this->belongsTo(Deputy::class, 'from_deputy_id');
    }

    public function toDeputy(): BelongsTo
    {
        return $this->belongsTo(Deputy::class, 'to_deputy_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** مجموع ساعاتی که این معاونت در این ماه به دیگران واگذار کرده. */
    public static function grantedBy(int $deputyId, int $jalaliYear, int $jalaliMonth): string
    {
        return (string) (self::where('from_deputy_id', $deputyId)
            ->where('jalali_year', $jalaliYear)->where('jalali_month', $jalaliMonth)
            ->sum('hours') ?: '0');
    }

    /** مجموع ساعاتی که این معاونت در این ماه از دیگران دریافت کرده. */
    public static function receivedBy(int $deputyId, int $jalaliYear, int $jalaliMonth): string
    {
        return (string) (self::where('to_deputy_id', $deputyId)
            ->where('jalali_year', $jalaliYear)->where('jalali_month', $jalaliMonth)
            ->sum('hours') ?: '0');
    }

    /** اثر خالص واگذاری‌ها روی ظرفیت این معاونت: دریافتی منهای واگذارشده. */
    public static function netFor(int $deputyId, int $jalaliYear, int $jalaliMonth): string
    {
        return bcsub(
            self::receivedBy($deputyId, $jalaliYear, $jalaliMonth),
            self::grantedBy($deputyId, $jalaliYear, $jalaliMonth),
            2
        );
    }
}
