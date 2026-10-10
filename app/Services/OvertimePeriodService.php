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

        $period = OvertimePeriod::create([
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
        $this->syncPeriodSpecialItems($period);

        return $period;
    }

    /** Persist each eligible HR item at its shared value on all existing periods. */
    public function syncSpecialItem(SpecialItem $item): void
    {
        $personnelIds = $item->is_active
            ? $item->eligiblePersonnel()->where('is_eligible', true)->pluck('personnel_id')
            : collect();
        OvertimePeriod::whereIn('personnel_id', $personnelIds)->get()->each(function (OvertimePeriod $period) use ($item) {
            $this->writeHrItemLine($period, $item, auth()->id());
        });
        \App\Models\OvertimeSpecialItemLine::where('special_item_id', $item->id)
            ->whereNotIn('overtime_period_id', OvertimePeriod::whereIn('personnel_id', $personnelIds)->select('id'))
            ->delete();
    }

    public function syncPeriodSpecialItems(OvertimePeriod $period): void
    {
        $items = SpecialItem::query()->where('is_active', true)
            ->whereHas('eligiblePersonnel', fn ($q) => $q->where('personnel_id', $period->personnel_id)->where('is_eligible', true))->get();
        foreach ($items as $item) {
            $this->writeHrItemLine($period, $item, null);
        }
    }

    private function writeHrItemLine(OvertimePeriod $period, SpecialItem $item, ?int $actorId): void
    {
        $values = [
            'title_snap' => $item->title,
            'hours' => $item->default_value,
            'requires_deputy_approval' => false,
            'deputy_approved' => true,
        ];
        if ($actorId !== null) {
            $values['entered_by'] = $actorId;
        }
        $period->specialItemLines()->updateOrCreate(['special_item_id' => $item->id], $values);
    }

    /**
     * اعمال مستقیم مقدار یک آیتم ویژه‌ی «مسئول ثبت = HR» روی دوره‌ی پرسنل - بدون نیاز به
     * تایید مدیر/معاون و حتی روی دوره‌ی قفل/نهایی‌شده (طبق تصمیم پروژه). مقدار قبلی همان
     * آیتم برای همان دوره (در صورت وجود) به‌روزرسانی می‌شود، نه تکراری اضافه می‌شود.
     */
    public function applyHrSpecialItem(User $personnel, SpecialItem $item, int $jalaliYear, int $jalaliMonth, string $hours, int $actorId): void
    {
        $period = $this->findOrCreateDraft($personnel, $jalaliYear, $jalaliMonth);

        $this->writeHrItemLine($period, $item, $actorId);
    }
}
