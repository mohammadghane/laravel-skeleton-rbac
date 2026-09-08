<?php

namespace App\Http\Controllers\Admin\Overtime;

use App\Http\Controllers\Controller;
use App\Models\Deputy;
use App\Models\Unit;
use App\Services\OvertimeReportService;
use App\Support\Jalali;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * گزارش‌گیری و داشبورد اضافه‌کار. دسترسی هر کاربر به یک محدوده
 * (واحد/معاونت/کل سازمان) بر اساس موقعیت او در چارت + permission تعیین می‌شود:
 *   - HR (overtime/hr-manage) و مدیرعامل (overtime/ceo-approve): هر محدوده‌ای، از جمله کل سازمان
 *   - معاون: فقط محدوده‌ی معاونت خودش (و واحدهای زیرمجموعه‌اش)
 *   - مدیر واحد: فقط محدوده‌ی واحد خودش
 * اگر scope/id در query پاس داده نشود، پیش‌فرض محدوده‌ی خودِ کاربر است.
 */
class OvertimeReportController extends Controller
{
    public function __construct(private readonly OvertimeReportService $reports)
    {
    }

    /**
     * داشبورد مدیریتی: مصرف سقف به تفکیک گروه، هشدارها، مقایسه‌ی معاونت‌ها،
     * رتبه‌بندی پرسنل و روند چند ماه اخیر - همه در یک صفحه.
     *
     * هر بخش بسته به دسترسی کاربر نمایش داده می‌شود؛ هشدارها و مقایسه‌ی معاونت‌ها فقط
     * برای HR/مدیرعامل معنا دارند (دید کل سازمان لازم دارند).
     */
    public function dashboard(Request $request): View
    {
        $user = $request->user();
        [$year, $month] = $this->period($request);
        [$unitId, $deputyId] = $this->resolveScope($request);

        $isPrivileged = $user->hasPermission('overtime/hr-manage') || $user->hasPermission('overtime/ceo-approve');

        return view('admin.overtime.reports.dashboard', [
            'year' => $year,
            'month' => $month,
            'isPrivileged' => $isPrivileged,
            'normal' => $this->reports->normalConsumptionByPosition($year, $month, $unitId, $deputyId),
            'excess' => $unitId ? collect() : $this->reports->excessConsumptionByPosition($year, $month, $deputyId),
            'warnings' => $isPrivileged ? $this->reports->ceilingWarnings($year, $month) : collect(),
            'comparison' => $isPrivileged ? $this->reports->deputyComparison($year, $month) : collect(),
            'ranking' => $this->reports->ranking($year, $month, $isPrivileged ? null : $deputyId)->take(10),
            'history' => $this->reports->monthlyHistory($unitId, $deputyId, 6),
            'missing' => $this->reports->personnelWithoutRecord($year, $month, $unitId, $deputyId),
        ]);
    }

    /** گزارش ماهانه‌ی تفصیلی پرسنل - جدول کامل با امکان خروجی اکسل. */
    public function personnelPage(Request $request): View
    {
        [$year, $month] = $this->period($request);
        [$unitId, $deputyId] = $this->resolveScope($request);

        $rows = $this->reports->personnelReport($year, $month, $unitId, $deputyId);

        // جست‌وجو روی خروجی گزارش (که از قبل در حافظه است) - نام پرسنل، واحد یا سمت.
        if ($request->filled('q')) {
            $term = mb_strtolower((string) $request->input('q'));

            $rows = $rows->filter(function (array $row) use ($term) {
                $haystack = mb_strtolower(implode(' ', array_filter([
                    $row['personnel_name'] ?? '', $row['unit'] ?? '', $row['deputy'] ?? '',
                    $row['position'] ?? '', $row['contract_type'] ?? '',
                ])));

                return str_contains($haystack, $term);
            })->values();
        }

        return view('admin.overtime.reports.personnel', [
            'year' => $year,
            'month' => $month,
            'rows' => $rows,
            'canExport' => $request->user()->hasPermission('overtime/hr-manage'),
        ]);
    }

    /** جدول مصرف سقف عادی و مازاد - هر دو به تفکیک سمت سازمانی. */
    public function consumption(Request $request): JsonResponse
    {
        [$year, $month] = $this->period($request);
        [$unitId, $deputyId] = $this->resolveScope($request);

        $data = ['normal' => $this->reports->normalConsumptionByPosition($year, $month, $unitId, $deputyId)];

        // مصرف مازاد فقط در سطح معاونت/کل سازمان معنا دارد، نه یک واحد به‌تنهایی
        if (!$unitId) {
            $data['excess'] = $this->reports->excessConsumptionByPosition($year, $month, $deputyId);
        }

        return response()->json(['success' => true, 'jalali_year' => $year, 'jalali_month' => $month, 'data' => $data]);
    }

    /** ریز گزارش ماهانه‌ی تک‌تک پرسنل یک محدوده - همان چیزی که مدیر واحد/معاونت برای ماه جاری یا گذشته می‌بینند. */
    public function personnel(Request $request): JsonResponse
    {
        [$year, $month] = $this->period($request);
        [$unitId, $deputyId] = $this->resolveScope($request);

        return response()->json([
            'success' => true,
            'jalali_year' => $year, 'jalali_month' => $month,
            'data' => $this->reports->personnelReport($year, $month, $unitId, $deputyId),
        ]);
    }

    /** پرسنلی که هنوز برای این ماه اضافه‌کاری ثبت نشده - برای پیگیری. */
    public function missing(Request $request): JsonResponse
    {
        [$year, $month] = $this->period($request);
        [$unitId, $deputyId] = $this->resolveScope($request);

        return response()->json([
            'success' => true,
            'data' => $this->reports->personnelWithoutRecord($year, $month, $unitId, $deputyId),
        ]);
    }

    /** هشدار نزدیک‌شدن/عبور از سقف - فقط HR و مدیرعامل (دید کل سازمان لازم دارد). */
    public function warnings(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('overtime/hr-manage') || $request->user()->hasPermission('overtime/ceo-approve'), 403);

        [$year, $month] = $this->period($request);
        $threshold = (int) $request->input('threshold', 90);

        return response()->json(['success' => true, 'data' => $this->reports->ceilingWarnings($year, $month, $threshold)]);
    }

    /** نمودار مقایسه‌ای و میانگین اضافه‌کار همه‌ی معاونت‌ها - فقط HR و مدیرعامل. */
    public function deputyComparison(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('overtime/hr-manage') || $request->user()->hasPermission('overtime/ceo-approve'), 403);

        [$year, $month] = $this->period($request);

        return response()->json(['success' => true, 'data' => $this->reports->deputyComparison($year, $month)]);
    }

    /** رتبه‌بندی پرسنل از بیشترین به کمترین - در سطح یک معاونت (خودِ معاون) یا کل شرکت (HR/مدیرعامل). */
    public function ranking(Request $request): JsonResponse
    {
        [$year, $month] = $this->period($request);
        $user = $request->user();

        if ($request->filled('deputy_id') || !($user->hasPermission('overtime/hr-manage') || $user->hasPermission('overtime/ceo-approve'))) {
            [, $deputyId] = $this->resolveScope($request);
        } else {
            $deputyId = null; // کل شرکت
        }

        return response()->json(['success' => true, 'data' => $this->reports->ranking($year, $month, $deputyId)]);
    }

    /** روند مصرف یک محدوده در N ماه اخیر - برای نمودار روند داشبورد. */
    public function history(Request $request): JsonResponse
    {
        [$unitId, $deputyId] = $this->resolveScope($request);
        $monthsBack = min(24, max(1, (int) $request->input('months', 6)));

        return response()->json(['success' => true, 'data' => $this->reports->monthlyHistory($unitId, $deputyId, $monthsBack)]);
    }

    /** @return array{0:int,1:int} [jalali_year, jalali_month] - پیش‌فرض ماه جاری. */
    private function period(Request $request): array
    {
        return [
            (int) $request->input('jalali_year', Jalali::currentYear()),
            (int) $request->input('jalali_month', Jalali::currentMonth()),
        ];
    }

    /**
     * محدوده‌ی مجاز کاربر جاری را برمی‌گرداند: [unitId, deputyId] (فقط یکی پر است، یا هر دو
     * null برای کل سازمان). HR/مدیرعامل می‌توانند هر scope/id دلخواهی در query بدهند؛ سایرین
     * محدود به موقعیت واقعی خودشان در چارت هستند، حتی اگر پارامتر دیگری بفرستند.
     */
    private function resolveScope(Request $request): array
    {
        $user = $request->user();
        $isPrivileged = $user->hasPermission('overtime/hr-manage') || $user->hasPermission('overtime/ceo-approve') || $user->hasPermission('overtime/reports');

        if ($isPrivileged) {
            $scope = $request->input('scope'); // unit | deputy | organization
            $id = $request->integer('id') ?: null;

            return match ($scope) {
                'unit' => [$id, null],
                'deputy' => [null, $id],
                default => [null, null],
            };
        }

        if ($user->isDeputyHead()) {
            $deputy = Deputy::where('head_user_id', $user->id)->firstOrFail();

            return [null, $deputy->id];
        }

        if ($user->isUnitManager()) {
            $unit = Unit::where('manager_user_id', $user->id)->firstOrFail();

            return [$unit->id, null];
        }

        abort(403, __('شما دسترسی مشاهده‌ی گزارش اضافه‌کار ندارید.'));
    }
}
