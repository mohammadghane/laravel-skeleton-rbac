<?php

namespace App\Models;

use App\Enums\OvertimePeriodStatus;
use App\Support\Jalali;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * رکورد اصلی گردش کار اضافه‌کار: یک ردیف به ازای هر «پرسنل + ماه شمسی».
 * تمام انتقال‌های state باید از طریق متدهای transitionTo* این مدل انجام شوند تا
 * overtime_status_history همیشه کامل باشد - هرگز مستقیم روی ستون status ننویسید.
 *
 * منطق کنترل سقف (چک کردن مجموع گروه/سمت قبل از هر تغییر) عمداً اینجا نیست؛
 * در App\Services\OvertimeCeilingService پیاده‌سازی می‌شود تا این مدل ساده و قابل تست بماند.
 */
class OvertimePeriod extends Model
{
    protected $fillable = [
        'personnel_id', 'jalali_year', 'jalali_month', 'status',
        'normal_hours', 'normal_ceiling_snapshot',
        'excess_hours', 'excess_ceiling_snapshot',
        'rial_coefficient', 'worked_days_count',
        'unit_id_snap', 'unit_title_snap',
        'deputy_id_snap', 'deputy_title_snap',
        'position_id_snap', 'position_title_snap',
        'contract_type_id_snap', 'contract_type_title_snap',
        'submitted_by', 'manager_locked_at',
        'deputy_reviewed_by', 'deputy_reviewed_at',
        'ceo_reviewed_by', 'ceo_reviewed_at',
        'finalized_at',
    ];

    protected $casts = [
        'status' => OvertimePeriodStatus::class,
        'normal_hours' => 'decimal:2',
        'normal_ceiling_snapshot' => 'decimal:2',
        'excess_hours' => 'decimal:2',
        'excess_ceiling_snapshot' => 'decimal:2',
        'rial_coefficient' => 'decimal:2',
        'manager_locked_at' => 'datetime',
        'deputy_reviewed_at' => 'datetime',
        'ceo_reviewed_at' => 'datetime',
        'finalized_at' => 'datetime',
    ];

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'personnel_id');
    }

    public function unitSnap(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id_snap');
    }

    public function deputySnap(): BelongsTo
    {
        return $this->belongsTo(Deputy::class, 'deputy_id_snap');
    }

    public function positionSnap(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id_snap');
    }

    public function contractTypeSnap(): BelongsTo
    {
        return $this->belongsTo(ContractType::class, 'contract_type_id_snap');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function deputyReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deputy_reviewed_by');
    }

    public function ceoReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ceo_reviewed_by');
    }

    public function specialItemLines(): HasMany
    {
        return $this->hasMany(OvertimeSpecialItemLine::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OvertimeStatusHistory::class)->latest();
    }

    /** جمع ساعت اضافه‌کار نهایی این پرسنل برای این ماه = عادی + مازاد + مجموع آیتم‌های ویژه. */
    public function totalHours(): string
    {
        $specialTotal = $this->specialItemLines->sum('hours');

        return bcadd(
            bcadd((string) $this->normal_hours, (string) $this->excess_hours, 2),
            (string) $specialTotal,
            2
        );
    }

    public function periodLabel(): string
    {
        return Jalali::periodLabel($this->jalali_year, $this->jalali_month);
    }

    /**
     * انتقال وضعیت + ثبت خودکار در overtime_status_history. actorId را همیشه صراحتاً
     * پاس بدهید (auth()->id() در لایه‌ی کنترلر/سرویس)، تا این مدل به auth() وابسته نباشد
     * و در تست‌ها/دستورات کنسول هم قابل استفاده بماند.
     */
    public function transitionTo(OvertimePeriodStatus $to, ?int $actorId, ?string $note = null): void
    {
        $from = $this->status;

        $this->status = $to;
        $this->save();

        $this->statusHistory()->create([
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'actor_id' => $actorId,
            'note' => $note,
        ]);
    }
}
