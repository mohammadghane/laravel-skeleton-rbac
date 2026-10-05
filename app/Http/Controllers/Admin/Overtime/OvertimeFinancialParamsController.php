<?php

namespace App\Http\Controllers\Admin\Overtime;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Overtime\OvertimeFinancialParamsRequest;
use App\Models\OvertimePeriod;
use App\Models\User;
use App\Services\OvertimePeriodService;
use App\Support\Jalali;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** تنظیم پارامترهای مالی اضافه‌کار توسط مدیر منابع انسانی. */
class OvertimeFinancialParamsController extends Controller
{
    public function __construct(
        private readonly OvertimePeriodService $periodService,
    ) {
    }

    /**
     * فهرست پارامترهای مالی برای پرسنل - فقط مدیر منابع انسانی.
     */
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('overtime/hr-manage'), 403);

        $year = (int) $request->input('jalali_year', Jalali::currentYear());
        $month = (int) $request->input('jalali_month', Jalali::currentMonth());

        // جست‌وجوی پرسنل
        $query = User::with(['unit', 'deputy', 'position', 'contractType']);
        
        if ($request->filled('q')) {
            $term = mb_strtolower((string) $request->input('q'));
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', '%' . $term . '%')
                  ->orWhere('family', 'like', '%' . $term . '%')
                  ->orWhere('username', 'like', '%' . $term . '%');
            });
        }

        $personnel = $query->orderBy('name')->paginate(20);

        // یافتن دوره‌ها برای این پرسنل در این ماه
        $personnelIds = $personnel->pluck('id');
        $periods = OvertimePeriod::where('jalali_year', $year)
            ->where('jalali_month', $month)
            ->whereIn('personnel_id', $personnelIds)
            ->get()
            ->keyBy('personnel_id');

        return view('admin.overtime.financial-params.index', [
            'personnel' => $personnel,
            'periods' => $periods,
            'year' => $year,
            'month' => $month,
        ]);
    }

    /**
     * ویرایش پارامترهای مالی یک پرسنل.
     */
    public function edit(Request $request, User $personnel): View|JsonResponse
    {
        abort_unless($request->user()->hasPermission('overtime/hr-manage'), 403);

        $year = (int) $request->input('jalali_year', Jalali::currentYear());
        $month = (int) $request->input('jalali_month', Jalali::currentMonth());

        $period = OvertimePeriod::where('personnel_id', $personnel->id)
            ->where('jalali_year', $year)
            ->where('jalali_month', $month)
            ->first();

        $data = [
            'personnel' => $personnel->load(['unit', 'deputy', 'position', 'contractType']),
            'period' => $period,
            'year' => $year,
            'month' => $month,
        ];

        if ($request->ajax() && $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('admin.overtime.financial-params.form', $data);
        }

        return view('admin.overtime.financial-params.edit', $data);
    }

    /**
     * ذخیره پارامترهای مالی برای یک پرسنل.
     */
    public function update(OvertimeFinancialParamsRequest $request, User $personnel): JsonResponse
    {
        $period = $this->periodService->findOrCreateDraft(
            $personnel, (int) $request->input('jalali_year'), (int) $request->input('jalali_month')
        );

        $period->update($request->only(['rial_coefficient', 'worked_days_count']));

        return response()->json(['success' => true, 'message' => __('پارامترهای مالی با موفقیت ذخیره شد.'), 'data' => $period]);
    }

    /**
     * ذخیره گروهی پارامترهای مالی برای چند پرسنل.
     */
    public function bulkStore(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('overtime/hr-manage'), 403);

        $validated = $request->validate([
            'rial_coefficient' => ['nullable', 'numeric', 'min:0'],
            'worked_days_count' => ['nullable', 'integer', 'min:0', 'max:31'],
            'jalali_year' => ['required', 'integer', 'min:1390', 'max:1450'],
            'jalali_month' => ['required', 'integer', 'min:1', 'max:12'],
            'personnel_ids' => ['nullable', 'string'],
            'apply_to_all' => ['required', 'boolean'],
        ]);

        $applyAll = (bool) $validated['apply_to_all'];
        $year = (int) $validated['jalali_year'];
        $month = (int) $validated['jalali_month'];
        $coefficient = $request->filled('rial_coefficient') ? (string) $request->input('rial_coefficient') : null;
        $workedDays = $request->filled('worked_days_count') ? (int) $request->input('worked_days_count') : null;

        // اگر apply_to_all فعال است، همه پرسنل را انتخاب کن
        if ($applyAll) {
            $personnel = User::orderBy('name')->get();
        } else {
            // در غیر این صورت، فقط پرسنل انتخاب‌شده را انتخاب کن
            $personnelIds = json_decode($validated['personnel_ids'], true);
            if (!is_array($personnelIds) || empty($personnelIds)) {
                return response()->json(['success' => false, 'message' => __('لیست پرسنل نامعتبر است.')], 422);
            }

            $personnel = User::whereIn('id', $personnelIds)->get();
        }

        $updated = 0;
        $skipped = [];

        foreach ($personnel as $person) {
            $label = trim($person->name . ' ' . $person->family);

            try {
                $period = $this->periodService->findOrCreateDraft($person, $year, $month);

                if ($coefficient !== null) {
                    $period->rial_coefficient = $coefficient;
                }
                if ($workedDays !== null) {
                    $period->worked_days_count = $workedDays;
                }

                $period->save();
                $updated++;
            } catch (\Throwable $e) {
                $skipped[] = $label . ' — ' . $e->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'message' => $updated > 0
                ? __('پارامترهای مالی برای :count نفر ذخیره شد.', ['count' => $updated])
                : __('هیچ رکوردی ذخیره نشد.'),
            'updated' => $updated,
            'skipped' => $skipped,
        ]);
    }
}
