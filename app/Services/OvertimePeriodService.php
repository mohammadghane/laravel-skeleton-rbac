<?php

namespace App\Services;

use App\Enums\OvertimePeriodStatus;
use App\Models\OvertimePeriod;
use App\Models\SpecialItem;
use App\Models\User;

/**
 * منطق مشترک ایجاد/دسترسی به OvertimePeriod - استفاده‌شده هم توسط گردش کار اصلی
 * (OvertimePeriodController) و هم مسیر مستقل ثبت آیتم ویژه توسط HR (SpecialItemValueController)
 * تا اسنپ‌شات چارت سازمانی همیشه یکسان و در یک‌جا ساخته شود.
 */
class OvertimePeriodService
{
    /** دوره‌ی موجود پرسنل+ماه را برمی‌گرداند یا با اسنپ‌شات وضعیت فعلی چارت او می‌سازد (Draft). */
    public function findOrCreateDraft(User $personnel, int $jalaliYear, int $jalaliMonth): OvertimePeriod
    {
        $existing = OvertimePeriod::where('personnel_id', $personnel->id)
            ->where('jalali_year', $jalaliYear)
            ->where('jalali_month', $jalaliMonth)
            ->first();

        if ($existing) {
            return $existing;
        }

        return OvertimePeriod::create([
            'personnel_id' => $personnel->id,
            'jalali_year' => $jalaliYear,
            'jalali_month' => $jalaliMonth,
            'status' => OvertimePeriodStatus::Draft,
            'unit_id_snap' => $personnel->unit_id,
            'unit_title_snap' => $personnel->unit?->title,
            'deputy_id_snap' => $personnel->effectiveDeputyId(),
            'deputy_title_snap' => $personnel->deputy?->title ?? $personnel->unit?->deputy?->title,
            'position_id_snap' => $personnel->position_id,
            'position_title_snap' => $personnel->position?->title,
            'contract_type_id_snap' => $personnel->contract_type_id,
            'contract_type_title_snap' => $personnel->contractType?->title,
        ]);
    }

    /**
     * اعمال مستقیم مقدار یک آیتم ویژه‌ی «مسئول ثبت = HR» روی دوره‌ی پرسنل - بدون نیاز به
     * تایید مدیر/معاون و حتی روی دوره‌ی قفل/نهایی‌شده (طبق تصمیم پروژه). مقدار قبلی همان
     * آیتم برای همان دوره (در صورت وجود) به‌روزرسانی می‌شود، نه تکراری اضافه می‌شود.
     */
    public function applyHrSpecialItem(User $personnel, SpecialItem $item, int $jalaliYear, int $jalaliMonth, string $hours, int $actorId): void
    {
        $period = $this->findOrCreateDraft($personnel, $jalaliYear, $jalaliMonth);

        $period->specialItemLines()->updateOrCreate(
            ['special_item_id' => $item->id],
            [
                'title_snap' => $item->title,
                'hours' => $hours,
                'requires_deputy_approval' => false,
                'deputy_approved' => true,
                'entered_by' => $actorId,
            ]
        );
    }
}
