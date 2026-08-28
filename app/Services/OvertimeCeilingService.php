<?php

namespace App\Services;

use App\Exceptions\OvertimeCeilingExceededException;
use App\Models\Deputy;
use App\Models\DeputyCapacityTransfer;
use App\Models\OvertimePeriod;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;

/**
 * هسته‌ی محاسباتی سقف اضافه‌کار (بند ۴.۳ و بازنگری بند ۴.۲).
 *
 *  - اضافه‌کار عادی (در اختیار مدیر واحد) - **بر اساس سمت سازمانی**:
 *      ۱) سطح واحد: مجموع ساعات پرسنلِ همان سمت در همان واحد
 *      ۲) سطح معاونت: مجموع ساعات پرسنلِ همان سمت در کل معاونت
 *    هر دو باید هم‌زمان رعایت شوند - سقف سطح واحد، سقف سطح معاونت را جایگزین نمی‌کند.
 *
 *    توجه: تا پیش از بازنگری سند، مبنای این سقف «نوع قرارداد» بود. سند اصلاح‌شده مثال
 *    عددی را بر اساس سمت می‌دهد (کارشناس ۹۰، رئیس گروه ۱۱۰، مدیر واحد ۱۲۰، …) و تبصره‌ی
 *    اول صراحتاً می‌گوید «تعداد پرسنل × عدد مشخص شده بر اساس سمت».
 *
 *  - اضافه‌کار مازاد (در اختیار معاونت) - بر اساس سمت سازمانی، در دو سطح:
 *      ۱) سقف همان سمت در آن معاونت
 *      ۲) ظرفیت کل معاونت، پس از اعمال واگذاری‌های بین معاونت‌ها (تبصره‌ی اول)
 *
 * محدودیت در سطح فرد اعمال نمی‌شود - یک پرسنل می‌تواند بیشتر یا کمتر از سهم میانگین خود
 * داشته باشد، فقط مجموعِ محدوده نباید از سقف تجاوز کند (بند ۴.۴).
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
        if (!$personnel->position_id) {
            throw new OvertimeCeilingExceededException(
                __('این پرسنل سمت سازمانی ندارد و امکان ثبت اضافه‌کار عادی برای او وجود ندارد.'),
                scopeLabel: '-', ceiling: '0', used: '0', attempted: $newHours,
            );
        }

        $position = Position::findOrFail($personnel->position_id);
        $ceilingVersion = $position->ceilingFor($jalaliYear, $jalaliMonth);

        if (!$ceilingVersion || $ceilingVersion->manager_ceiling_per_person === null) {
            throw new OvertimeCeilingExceededException(
                __('برای این سمت سازمانی در این ماه، سقف اضافه‌کار عادی توسط منابع انسانی تعریف نشده است.'),
                scopeLabel: $position->title, ceiling: '0', used: '0', attempted: $newHours,
            );
        }

        $ceilingPerPerson = (string) $ceilingVersion->manager_ceiling_per_person;

        // سطح ۱: واحد پرسنل (اگر واحد دارد - پرسنل مستقیمِ زیر معاونت این لایه را ندارند)
        if ($personnel->unit_id) {
            $this->assertScopeWithinCeiling(
                scopeLabel: __('واحد') . ' «' . ($personnel->unit?->title) . '» - ' . $position->title,
                headcount: User::where('unit_id', $personnel->unit_id)->where('position_id', $position->id)->count(),
                ceilingPerPerson: $ceilingPerPerson,
                usedHours: $this->sumNormalHours($jalaliYear, $jalaliMonth, $position->id, unitId: $personnel->unit_id, excludePeriodId: $excludePeriodId),
                newHours: $newHours,
            );
        }

        // سطح ۲: کل معاونت (همه‌ی واحدهای زیرمجموعه + پرسنل مستقیم)
        $deputyId = $personnel->effectiveDeputyId();
        if ($deputyId) {
            $deputy = Deputy::findOrFail($deputyId);
            $this->assertScopeWithinCeiling(
                scopeLabel: __('معاونت') . ' «' . $deputy->title . '» - ' . $position->title,
                headcount: $this->deputyHeadcountByPosition($deputyId, $position->id),
                ceilingPerPerson: $ceilingPerPerson,
                usedHours: $this->sumNormalHours($jalaliYear, $jalaliMonth, $position->id, deputyId: $deputyId, excludePeriodId: $excludePeriodId),
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

        // سطح ۱: سقف همان سمت در این معاونت (تعداد پرسنل آن سمت × سقف مازاد هر نفر).
        //
        // نکته: ظرفیتِ دریافتی از معاونت‌های دیگر (تبصره‌ی اول) به‌صورت «سرریز» به این سقف
        // اضافه می‌شود، وگرنه ظرفیت واگذارشده هرگز قابل استفاده نبود و کل قابلیت واگذاری
        // بی‌اثر می‌شد. کنترل واقعیِ سوءاستفاده در سطح ۲ (ظرفیت کل معاونت) انجام می‌شود.
        $positionCeiling = bcmul(
            (string) $this->deputyHeadcountByPosition($deputyId, $position->id),
            (string) $ceilingVersion->ceiling_per_person,
            2
        );

        $received = DeputyCapacityTransfer::receivedBy($deputyId, $jalaliYear, $jalaliMonth);
        $positionCeilingWithHeadroom = bcadd($positionCeiling, $received, 2);

        $positionUsed = $this->sumExcessHours($jalaliYear, $jalaliMonth, $position->id, $deputyId, excludePeriodId: $excludePeriodId);

        if (bccomp(bcadd($positionUsed, $newHours, 2), $positionCeilingWithHeadroom, 2) === 1) {
            throw new OvertimeCeilingExceededException(
                __('سقف مجاز :scope تجاوز می‌کند: سقف کل :ceiling ساعت، مصرف‌شده تاکنون :used ساعت، مقدار درخواستی :attempted ساعت.', [
                    'scope' => __('معاونت') . ' «' . $deputy->title . '» - ' . __('مازاد') . ' ' . $position->title,
                    'ceiling' => $positionCeilingWithHeadroom,
                    'used' => $positionUsed,
                    'attempted' => $newHours,
                ]),
                scopeLabel: __('معاونت') . ' «' . $deputy->title . '» - ' . __('مازاد') . ' ' . $position->title,
                ceiling: $positionCeilingWithHeadroom,
                used: $positionUsed,
                attempted: $newHours,
            );
        }

        // سطح ۲: ظرفیت کل معاونت پس از واگذاری‌ها (تبصره‌ی اول بازنگری بند ۴.۲).
        // اگر معاونتی بخشی از ظرفیتش را واگذار کرده باشد، دیگر نمی‌تواند از آن استفاده کند؛
        // و معاونتِ گیرنده می‌تواند بیش از سقف پایه‌ی خودش مصرف کند.
        $this->assertDeputyTotalExcessCapacity($deputy, $jalaliYear, $jalaliMonth, $newHours, $excludePeriodId);
    }

    /**
     * ظرفیت کل اضافه‌کار مازاد یک معاونت در یک ماه:
     *   ظرفیت پایه = Σ (تعداد پرسنل هر سمت × سقف مازاد آن سمت)
     *   ظرفیت مؤثر = ظرفیت پایه - واگذارشده + دریافت‌شده
     *
     * @throws OvertimeCeilingExceededException
     */
    private function assertDeputyTotalExcessCapacity(Deputy $deputy, int $jalaliYear, int $jalaliMonth, string $newHours, ?int $excludePeriodId): void
    {
        $baseCapacity = $this->deputyBaseExcessCapacity($deputy->id, $jalaliYear, $jalaliMonth);
        $effective = bcadd($baseCapacity, DeputyCapacityTransfer::netFor($deputy->id, $jalaliYear, $jalaliMonth), 2);

        $used = $this->sumDeputyExcessHours($deputy->id, $jalaliYear, $jalaliMonth, $excludePeriodId);
        $projected = bcadd($used, $newHours, 2);

        if (bccomp($projected, $effective, 2) === 1) {
            throw new OvertimeCeilingExceededException(
                __('ظرفیت کل اضافه‌کار مازاد معاونت «:deputy» تجاوز می‌کند: ظرفیت مؤثر :ceiling ساعت (پایه :base، خالص واگذاری :net)، مصرف‌شده :used ساعت، درخواستی :attempted ساعت.', [
                    'deputy' => $deputy->title,
                    'ceiling' => $effective,
                    'base' => $baseCapacity,
                    'net' => DeputyCapacityTransfer::netFor($deputy->id, $jalaliYear, $jalaliMonth),
                    'used' => $used,
                    'attempted' => $newHours,
                ]),
                scopeLabel: __('معاونت') . ' ' . $deputy->title,
                ceiling: $effective, used: $used, attempted: $newHours,
            );
        }
    }

    /** ظرفیت پایه‌ی مازاد یک معاونت: مجموع (تعداد پرسنل هر سمت × سقف مازاد همان سمت). */
    public function deputyBaseExcessCapacity(int $deputyId, int $jalaliYear, int $jalaliMonth): string
    {
        $unitIds = Unit::where('deputy_id', $deputyId)->pluck('id');

        $positionIds = User::query()
            ->whereNotNull('position_id')
            ->where(function ($q) use ($deputyId, $unitIds) {
                $q->where('deputy_id', $deputyId)->orWhereIn('unit_id', $unitIds);
            })
            ->distinct()->pluck('position_id');

        $total = '0';

        foreach (Position::whereIn('id', $positionIds)->get() as $position) {
            $ceiling = $position->ceilingFor($jalaliYear, $jalaliMonth);

            if (!$ceiling) {
                continue;
            }

            $headcount = $this->deputyHeadcountByPosition($deputyId, $position->id);
            $total = bcadd($total, bcmul((string) $headcount, (string) $ceiling->ceiling_per_person, 2), 2);
        }

        return $total;
    }

    /** مجموع اضافه‌کار مازادِ ثبت‌شده برای کل پرسنل یک معاونت در یک ماه. */
    private function sumDeputyExcessHours(int $deputyId, int $jalaliYear, int $jalaliMonth, ?int $excludePeriodId = null): string
    {
        $unitIds = Unit::where('deputy_id', $deputyId)->pluck('id');

        $sum = OvertimePeriod::query()
            ->join('users', 'users.id', '=', 'overtime_periods.personnel_id')
            ->where('overtime_periods.jalali_year', $jalaliYear)
            ->where('overtime_periods.jalali_month', $jalaliMonth)
            ->where(function ($q) use ($deputyId, $unitIds) {
                $q->where('users.deputy_id', $deputyId)->orWhereIn('users.unit_id', $unitIds);
            })
            ->when($excludePeriodId, fn ($q) => $q->where('overtime_periods.id', '!=', $excludePeriodId))
            ->sum('overtime_periods.excess_hours');

        return (string) ($sum ?: '0');
    }

    /**
     * ظرفیت مازادِ باقی‌مانده‌ی یک معاونت - مبنای «چقدر می‌تواند واگذار کند».
     * منفی نمی‌شود (اگر بیش از ظرفیت مصرف شده باشد، صفر برمی‌گردد).
     */
    public function deputyRemainingExcessCapacity(int $deputyId, int $jalaliYear, int $jalaliMonth): string
    {
        $effective = bcadd(
            $this->deputyBaseExcessCapacity($deputyId, $jalaliYear, $jalaliMonth),
            DeputyCapacityTransfer::netFor($deputyId, $jalaliYear, $jalaliMonth),
            2
        );

        $remaining = bcsub($effective, $this->sumDeputyExcessHours($deputyId, $jalaliYear, $jalaliMonth), 2);

        return bccomp($remaining, '0', 2) === -1 ? '0.00' : $remaining;
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
     * مجموع normal_hours ثبت‌شده برای یک سمت سازمانی در یک ماه شمسی، در محدوده‌ی یک واحد
     * یا یک معاونت (دقیقاً یکی از unitId/deputyId را پاس بدهید).
     *
     * از بازنگری بند ۴.۲ به بعد، گروه‌بندی سقف عادی بر اساس «سمت» است نه «نوع قرارداد».
     */
    private function sumNormalHours(int $jalaliYear, int $jalaliMonth, int $positionId, ?int $unitId = null, ?int $deputyId = null, ?int $excludePeriodId = null): string
    {
        $query = OvertimePeriod::query()
            ->join('users', 'users.id', '=', 'overtime_periods.personnel_id')
            ->where('overtime_periods.jalali_year', $jalaliYear)
            ->where('overtime_periods.jalali_month', $jalaliMonth)
            ->where('users.position_id', $positionId)
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
