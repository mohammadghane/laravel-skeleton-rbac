<?php

namespace App\Services;

use App\Models\Deputy;
use App\Models\OvertimePeriod;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use App\Support\Jalali;
use Illuminate\Support\Collection;

/**
 * لایه‌ی گزارش‌گیری اضافه‌کار - شرح خدمات. تمام متدها فقط داده برمی‌گردانند
 * (Collection/array)؛ فیلتر دسترسی/اسکوپ کاربر جاری در کنترلر انجام می‌شود، نه اینجا،
 * تا این سرویس در Job/Command صدور خودکار هشدار هم قابل استفاده مجدد باشد.
 */
class OvertimeReportService
{
    /**
     * مصرف اضافه‌کار عادی به تفکیک **سمت سازمانی**، در یک محدوده (واحد/معاونت/کل سازمان).
     * دقیقاً یکی از unitId/deputyId را پاس بدهید؛ هیچ‌کدام = کل سازمان.
     *
     * از بازنگری به بعد، مبنای سقف عادی «سمت» است نه «نوع قرارداد»؛ بنابراین
     * گزارش مصرف هم باید بر همان مبنا گروه‌بندی شود تا با موتور سقف هم‌خوان باشد.
     */
    public function normalConsumptionByPosition(int $year, int $month, ?int $unitId = null, ?int $deputyId = null): Collection
    {
        $personnelQuery = $this->scopedPersonnelQuery($unitId, $deputyId)->whereNotNull('position_id');

        return Position::query()
            ->whereIn('id', (clone $personnelQuery)->pluck('position_id')->unique())
            ->get()
            ->map(function (Position $position) use ($year, $month, $personnelQuery) {
                $headcount = (clone $personnelQuery)->where('position_id', $position->id)->count();
                $ceilingVersion = $position->ceilingFor($year, $month);
                $ceilingPerPerson = (string) ($ceilingVersion->manager_ceiling_per_person ?? '0');
                $totalCeiling = bcmul((string) $headcount, $ceilingPerPerson, 2);

                $used = (string) (OvertimePeriod::query()
                    ->join('users', 'users.id', '=', 'overtime_periods.personnel_id')
                    ->where('overtime_periods.jalali_year', $year)
                    ->where('overtime_periods.jalali_month', $month)
                    ->where('users.position_id', $position->id)
                    ->whereIn('users.id', (clone $personnelQuery)->pluck('id'))
                    ->sum('overtime_periods.normal_hours') ?: '0');

                return $this->buildConsumptionRow($position->id, $position->title, $headcount, $totalCeiling, $used);
            })
            ->values();
    }

    /** مصرف اضافه‌کار مازاد به تفکیک سمت سازمانی، در یک معاونت مشخص یا کل سازمان (deputyId=null). */
    public function excessConsumptionByPosition(int $year, int $month, ?int $deputyId = null): Collection
    {
        $personnelQuery = $this->scopedPersonnelQuery(null, $deputyId)->whereNotNull('position_id');

        return Position::query()
            ->whereIn('id', (clone $personnelQuery)->pluck('position_id')->unique())
            ->get()
            ->map(function (Position $position) use ($year, $month, $personnelQuery) {
                $headcount = (clone $personnelQuery)->where('position_id', $position->id)->count();
                $ceilingVersion = $position->ceilingFor($year, $month);
                $ceilingPerPerson = (string) ($ceilingVersion->ceiling_per_person ?? '0');
                $totalCeiling = bcmul((string) $headcount, $ceilingPerPerson, 2);

                $used = (string) (OvertimePeriod::query()
                    ->join('users', 'users.id', '=', 'overtime_periods.personnel_id')
                    ->where('overtime_periods.jalali_year', $year)
                    ->where('overtime_periods.jalali_month', $month)
                    ->where('users.position_id', $position->id)
                    ->whereIn('users.id', (clone $personnelQuery)->pluck('id'))
                    ->sum('overtime_periods.excess_hours') ?: '0');

                return $this->buildConsumptionRow($position->id, $position->title, $headcount, $totalCeiling, $used);
            })
            ->values();
    }

    /** گزارش ماهانه‌ی تک‌تک پرسنل یک محدوده (واحد/معاونت/کل سازمان) با ریز اضافه‌کار عادی/مازاد/ویژه. */
    public function personnelReport(int $year, int $month, ?int $unitId = null, ?int $deputyId = null): Collection
    {
        $personnelIds = $this->scopedPersonnelQuery($unitId, $deputyId)->pluck('id');

        return OvertimePeriod::query()
            ->with(['personnel.unit', 'personnel.deputy', 'personnel.position', 'personnel.contractType', 'specialItemLines'])
            ->where('jalali_year', $year)->where('jalali_month', $month)
            ->whereIn('personnel_id', $personnelIds)
            ->get()
            ->map(fn (OvertimePeriod $period) => [
                'personnel_id' => $period->personnel_id,
                'personnel_name' => trim($period->personnel->name . ' ' . $period->personnel->family),
                'unit' => $period->unitSnap?->title,
                'deputy' => $period->deputySnap?->title,
                'contract_type' => $period->contractTypeSnap?->title,
                'position' => $period->positionSnap?->title,
                'status' => $period->status->value,
                'status_label' => $period->status->label(),
                'normal_hours' => (string) $period->normal_hours,
                'excess_hours' => (string) $period->excess_hours,
                'special_hours' => (string) $period->specialItemLines->sum('hours'),
                'total_hours' => $period->totalHours(),
            ])
            ->sortByDesc(fn ($row) => (float) $row['total_hours'])
            ->values();
    }

    /** پرسنلی که هنوز برای این ماه دوره‌ی ثبت‌شده ندارند (برای پیگیری مدیر واحد/HR). */
    public function personnelWithoutRecord(int $year, int $month, ?int $unitId = null, ?int $deputyId = null): Collection
    {
        $registeredIds = OvertimePeriod::where('jalali_year', $year)->where('jalali_month', $month)->pluck('personnel_id');

        return $this->scopedPersonnelQuery($unitId, $deputyId)
            ->whereNotIn('id', $registeredIds)
            ->get(['id', 'name', 'family', 'unit_id', 'deputy_id']);
    }

    /**
     * هشدار نزدیک‌شدن/عبور از سقف - برای هر واحد و هر معاونت، مصرف نوع‌قرارداد و مصرف
     * سمت (در سطح معاونت) را چک می‌کند. $thresholdPercent پیش‌فرض ۹۰٪.
     */
    public function ceilingWarnings(int $year, int $month, int $thresholdPercent = 90): Collection
    {
        $warnings = collect();

        foreach (Unit::where('is_active', true)->get() as $unit) {
            foreach ($this->normalConsumptionByPosition($year, $month, unitId: $unit->id) as $row) {
                if ($row['percentage'] >= $thresholdPercent) {
                    $warnings->push($this->buildWarningRow('unit', $unit->id, $unit->title, $row));
                }
            }
        }

        foreach (Deputy::where('is_active', true)->get() as $deputy) {
            foreach ($this->normalConsumptionByPosition($year, $month, deputyId: $deputy->id) as $row) {
                if ($row['percentage'] >= $thresholdPercent) {
                    $warnings->push($this->buildWarningRow('deputy-normal', $deputy->id, $deputy->title, $row));
                }
            }
            foreach ($this->excessConsumptionByPosition($year, $month, deputyId: $deputy->id) as $row) {
                if ($row['percentage'] >= $thresholdPercent) {
                    $warnings->push($this->buildWarningRow('deputy-excess', $deputy->id, $deputy->title, $row));
                }
            }
        }

        return $warnings->sortByDesc('percentage')->values();
    }

    /** میانگین و مجموع اضافه‌کار هر معاونت - برای نمودار مقایسه‌ای معاونت‌ها. */
    public function deputyComparison(int $year, int $month): Collection
    {
        return Deputy::where('is_active', true)->get()->map(function (Deputy $deputy) use ($year, $month) {
            $personnelIds = $deputy->allPersonnelIds();
            $headcount = $personnelIds->count();

            $periods = OvertimePeriod::with('specialItemLines')
                ->where('jalali_year', $year)->where('jalali_month', $month)
                ->whereIn('personnel_id', $personnelIds)
                ->get();

            $total = $periods->reduce(fn ($carry, OvertimePeriod $p) => bcadd($carry, $p->totalHours(), 2), '0');

            return [
                'deputy_id' => $deputy->id,
                'deputy_title' => $deputy->title,
                'headcount' => $headcount,
                'total_hours' => $total,
                'average_per_person' => $headcount > 0 ? bcdiv($total, (string) $headcount, 2) : '0',
            ];
        })->sortByDesc(fn ($row) => (float) $row['total_hours'])->values();
    }

    /** رتبه‌بندی پرسنل از بیشترین به کمترین مجموع اضافه‌کار - در سطح یک معاونت یا کل شرکت. */
    public function ranking(int $year, int $month, ?int $deputyId = null): Collection
    {
        return $this->personnelReport($year, $month, deputyId: $deputyId); // از قبل نزولی مرتب است
    }

    /** روند مصرف یک محدوده (واحد یا معاونت) در N ماه اخیر - برای نمودار روند در داشبورد. */
    public function monthlyHistory(?int $unitId = null, ?int $deputyId = null, int $monthsBack = 6): Collection
    {
        return collect(Jalali::lastMonths($monthsBack))->map(function (array $ym) use ($unitId, $deputyId) {
            [$year, $month] = $ym;
            $personnelIds = $this->scopedPersonnelQuery($unitId, $deputyId)->pluck('id');

            $total = OvertimePeriod::with('specialItemLines')
                ->where('jalali_year', $year)->where('jalali_month', $month)
                ->whereIn('personnel_id', $personnelIds)
                ->get()
                ->reduce(fn ($carry, OvertimePeriod $p) => bcadd($carry, $p->totalHours(), 2), '0');

            return ['jalali_year' => $year, 'jalali_month' => $month, 'label' => Jalali::periodLabel($year, $month), 'total_hours' => $total];
        });
    }

    private function scopedPersonnelQuery(?int $unitId, ?int $deputyId)
    {
        if ($unitId) {
            return User::query()->where('unit_id', $unitId);
        }

        if ($deputyId) {
            $unitIds = Unit::where('deputy_id', $deputyId)->pluck('id');

            return User::query()->where(function ($q) use ($deputyId, $unitIds) {
                $q->where('deputy_id', $deputyId)->orWhereIn('unit_id', $unitIds);
            });
        }

        return User::query(); // کل سازمان
    }

    private function buildConsumptionRow(int $id, string $title, int $headcount, string $totalCeiling, string $used): array
    {
        $percentage = bccomp($totalCeiling, '0', 2) === 0
            ? 0.0
            : round(((float) $used / (float) $totalCeiling) * 100, 1);

        return [
            'id' => $id,
            'title' => $title,
            'headcount' => $headcount,
            'ceiling' => $totalCeiling,
            'used' => $used,
            'remaining' => bcsub($totalCeiling, $used, 2),
            'percentage' => $percentage,
        ];
    }

    private function buildWarningRow(string $type, int $scopeId, string $scopeTitle, array $row): array
    {
        return [
            'type' => $type, // unit | deputy-normal | deputy-excess
            'scope_id' => $scopeId,
            'scope_title' => $scopeTitle,
            'item_title' => $row['title'],
            'ceiling' => $row['ceiling'],
            'used' => $row['used'],
            'percentage' => $row['percentage'],
            'level' => $row['percentage'] >= 100 ? 'exceeded' : 'near',
        ];
    }
}
