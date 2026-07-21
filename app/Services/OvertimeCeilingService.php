<?php

namespace App\Services;

use App\Exceptions\OvertimeCeilingExceededException;
use App\Models\ContractType;
use App\Models\Deputy;
use App\Models\OvertimePeriod;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * هسته‌ی محاسباتی سقف اضافه‌کار. سقف‌ها «مجزا» تعریف می‌شوند (طبق تایید کارفرما):
 *
 *  - اضافه‌کار عادی (بر اساس نوع قرارداد): در دو سطح مستقل چک می‌شود -
 *      ۱) سطح واحد (مدیر واحد): مجموع ساعات پرسنلِ همان نوع قرارداد در همان واحد
 *      ۲) سطح معاونت: مجموع ساعات پرسنلِ همان نوع قرارداد در کل معاونت (همه‌ی واحدها + مستقیم)
 *    هر دو باید هم‌زمان رعایت شوند - سقف سطح واحد، سقف سطح معاونت را جایگزین نمی‌کند.
 *
 *  - اضافه‌کار مازاد (بر اساس سمت سازمانی): فقط در سطح معاونت چک می‌شود، چون ثبت/تایید آن
 *    منحصراً در اختیار معاونت (و تایید نهایی مدیرعامل) است.
 *
 * محدودیت در سطح فرد اعمال نمی‌شود - یک پرسنل می‌تواند بیشتر یا کمتر از سهم میانگین خود
 * داشته باشد، فقط مجموعِ محدوده نباید از سقف تجاوز کند.
 */
class OvertimeCeilingService
{
    /**
     * پیش از ثبت/ویرایش اضافه‌کار عادی یک پرسنل، سقف واحد و سقف معاونت را چک می‌کند.
     * $excludePeriodId: هنگام ویرایش یک رکورد موجود، مقدار قبلی خودش از «مصرف‌شده» کم می‌شود.
     *
     * @throws OvertimeCeilingExceededException
     */
    public function assertNormalWithinCeiling(User $personnel, int $jalaliYear, int $jalaliMonth, string $newHours, ?int $excludePeriodId = null): void
    {
        if (!$personnel->contract_type_id) {
            throw new OvertimeCeilingExceededException(
                __('این پرسنل نوع قرارداد ندارد و امکان ثبت اضافه‌کار عادی برای او وجود ندارد.'),
                scopeLabel: '-', ceiling: '0', used: '0', attempted: $newHours,
            );
        }

        $contractType = ContractType::findOrFail($personnel->contract_type_id);
        $ceilingVersion = $contractType->ceilingFor($jalaliYear, $jalaliMonth);

        if (!$ceilingVersion) {
            throw new OvertimeCeilingExceededException(
                __('برای این نوع قرارداد در این ماه، سقفی توسط منابع انسانی تعریف نشده است.'),
                scopeLabel: $contractType->title, ceiling: '0', used: '0', attempted: $newHours,
            );
        }

        // سطح ۱: واحد پرسنل (اگر واحد دارد - پرسنل مستقیمِ زیر معاونت این لایه را ندارند)
        if ($personnel->unit_id) {
            $this->assertScopeWithinCeiling(
                scopeLabel: __('واحد') . ' «' . ($personnel->unit?->title) . '» - ' . $contractType->title,
                headcount: User::where('unit_id', $personnel->unit_id)->where('contract_type_id', $contractType->id)->count(),
                ceilingPerPerson: (string) $ceilingVersion->ceiling_per_person,
                usedHours: $this->sumNormalHours($jalaliYear, $jalaliMonth, $contractType->id, unitId: $personnel->unit_id, excludePeriodId: $excludePeriodId),
                newHours: $newHours,
            );
        }

        // سطح ۲: کل معاونت (همه‌ی واحدهای زیرمجموعه + پرسنل مستقیم)
        $deputyId = $personnel->effectiveDeputyId();
        if ($deputyId) {
            $deputy = Deputy::findOrFail($deputyId);
            $this->assertScopeWithinCeiling(
                scopeLabel: __('معاونت') . ' «' . $deputy->title . '» - ' . $contractType->title,
                headcount: $this->deputyHeadcountByContractType($deputyId, $contractType->id),
                ceilingPerPerson: (string) $ceilingVersion->ceiling_per_person,
                usedHours: $this->sumNormalHours($jalaliYear, $jalaliMonth, $contractType->id, deputyId: $deputyId, excludePeriodId: $excludePeriodId),
                newHours: $newHours,
            );
        }
    }

    /**
     * پیش از ثبت/ویرایش اضافه‌کار مازاد یک پرسنل، سقف کل معاونت (بر اساس سمت سازمانی) را چک می‌کند.
     *
     * @throws OvertimeCeilingExceededException
     */
    public function assertExcessWithinCeiling(User $personnel, int $jalaliYear, int $jalaliMonth, string $newHours, ?int $excludePeriodId = null): void
    {
        if (!$personnel->position_id) {
            throw new OvertimeCeilingExceededException(
                __('این پرسنل سمت سازمانی ندارد و امکان ثبت اضافه‌کار مازاد برای او وجود ندارد.'),
                scopeLabel: '-', ceiling: '0', used: '0', attempted: $newHours,
            );
        }

        $deputyId = $personnel->effectiveDeputyId();
        if (!$deputyId) {
            throw new OvertimeCeilingExceededException(
                __('این پرسنل به هیچ معاونتی متصل نیست.'),
                scopeLabel: '-', ceiling: '0', used: '0', attempted: $newHours,
            );
        }

        $position = Position::findOrFail($personnel->position_id);
        $ceilingVersion = $position->ceilingFor($jalaliYear, $jalaliMonth);

        if (!$ceilingVersion) {
            throw new OvertimeCeilingExceededException(
                __('برای این سمت سازمانی در این ماه، سقف مازادی تعریف نشده است.'),
                scopeLabel: $position->title, ceiling: '0', used: '0', attempted: $newHours,
            );
        }

        $deputy = Deputy::findOrFail($deputyId);

        $this->assertScopeWithinCeiling(
            scopeLabel: __('معاونت') . ' «' . $deputy->title . '» - ' . __('مازاد') . ' ' . $position->title,
            headcount: $this->deputyHeadcountByPosition($deputyId, $position->id),
            ceilingPerPerson: (string) $ceilingVersion->ceiling_per_person,
            usedHours: $this->sumExcessHours($jalaliYear, $jalaliMonth, $position->id, $deputyId, excludePeriodId: $excludePeriodId),
            newHours: $newHours,
        );
    }

    private function assertScopeWithinCeiling(string $scopeLabel, int $headcount, string $ceilingPerPerson, string $usedHours, string $newHours): void
    {
        $totalCeiling = bcmul((string) $headcount, $ceilingPerPerson, 2);
        $projectedUsed = bcadd($usedHours, $newHours, 2);

        if (bccomp($projectedUsed, $totalCeiling, 2) === 1) {
            throw new OvertimeCeilingExceededException(
                __('سقف مجاز :scope تجاوز می‌کند: سقف کل :ceiling ساعت، مصرف‌شده تاکنون :used ساعت، مقدار درخواستی :attempted ساعت.', [
                    'scope' => $scopeLabel,
                    'ceiling' => $totalCeiling,
                    'used' => $usedHours,
                    'attempted' => $newHours,
                ]),
                scopeLabel: $scopeLabel,
                ceiling: $totalCeiling,
                used: $usedHours,
                attempted: $newHours,
            );
        }
    }

    /** تعداد پرسنل یک نوع قرارداد مشخص، زیرمجموعه‌ی یک معاونت (مستقیم + از طریق همه‌ی واحدهایش). */
    private function deputyHeadcountByContractType(int $deputyId, int $contractTypeId): int
    {
        $unitIds = Unit::where('deputy_id', $deputyId)->pluck('id');

        return User::where('contract_type_id', $contractTypeId)
            ->where(function ($q) use ($deputyId, $unitIds) {
                $q->where('deputy_id', $deputyId)->orWhereIn('unit_id', $unitIds);
            })
            ->count();
    }

    /** تعداد پرسنل یک سمت مشخص، زیرمجموعه‌ی یک معاونت (مستقیم + از طریق همه‌ی واحدهایش). */
    private function deputyHeadcountByPosition(int $deputyId, int $positionId): int
    {
        $unitIds = Unit::where('deputy_id', $deputyId)->pluck('id');

        return User::where('position_id', $positionId)
            ->where(function ($q) use ($deputyId, $unitIds) {
                $q->where('deputy_id', $deputyId)->orWhereIn('unit_id', $unitIds);
            })
            ->count();
    }

    /**
     * مجموع normal_hours ثبت‌شده برای یک نوع قرارداد در یک ماه شمسی، در محدوده‌ی یک واحد
     * یا یک معاونت (دقیقاً یکی از unitId/deputyId را پاس بدهید).
     */
    private function sumNormalHours(int $jalaliYear, int $jalaliMonth, int $contractTypeId, ?int $unitId = null, ?int $deputyId = null, ?int $excludePeriodId = null): string
    {
        $query = OvertimePeriod::query()
            ->join('users', 'users.id', '=', 'overtime_periods.personnel_id')
            ->where('overtime_periods.jalali_year', $jalaliYear)
            ->where('overtime_periods.jalali_month', $jalaliMonth)
            ->where('users.contract_type_id', $contractTypeId)
            ->when($excludePeriodId, fn ($q) => $q->where('overtime_periods.id', '!=', $excludePeriodId));

        if ($unitId) {
            $query->where('users.unit_id', $unitId);
        } elseif ($deputyId) {
            $unitIds = Unit::where('deputy_id', $deputyId)->pluck('id');
            $query->where(function ($q) use ($deputyId, $unitIds) {
                $q->where('users.deputy_id', $deputyId)->orWhereIn('users.unit_id', $unitIds);
            });
        }

        return (string) ($query->sum('overtime_periods.normal_hours') ?: '0');
    }

    /** مجموع excess_hours ثبت‌شده برای یک سمت در یک ماه شمسی، در محدوده‌ی یک معاونت. */
    private function sumExcessHours(int $jalaliYear, int $jalaliMonth, int $positionId, int $deputyId, ?int $excludePeriodId = null): string
    {
        $unitIds = Unit::where('deputy_id', $deputyId)->pluck('id');

        $sum = OvertimePeriod::query()
            ->join('users', 'users.id', '=', 'overtime_periods.personnel_id')
            ->where('overtime_periods.jalali_year', $jalaliYear)
            ->where('overtime_periods.jalali_month', $jalaliMonth)
            ->where('users.position_id', $positionId)
            ->where(function ($q) use ($deputyId, $unitIds) {
                $q->where('users.deputy_id', $deputyId)->orWhereIn('users.unit_id', $unitIds);
            })
            ->when($excludePeriodId, fn ($q) => $q->where('overtime_periods.id', '!=', $excludePeriodId))
            ->sum('overtime_periods.excess_hours');

        return (string) ($sum ?: '0');
    }
}
