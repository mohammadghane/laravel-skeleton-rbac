<?php

namespace App\Http\Controllers\Admin\Overtime;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Overtime\PositionCeilingRequest;
use App\Models\Position;
use App\Support\Jalali;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** ثبت سقف اضافه‌کار مازاد برای یک سمت سازمانی در یک ماه شمسی - فقط مدیر منابع انسانی. */
class PositionCeilingController extends Controller
{

    public function index(Request $request, Position $position): View
    {
        $versions = $position->ceilingVersions()->with('creator')
            ->orderByDesc('jalali_year')->orderByDesc('jalali_month')->get();

        return view('admin.overtime.ceilings.position', [
            'position' => $position,
            'versions' => $versions,
            'headcount' => $position->personnel()->count(),
            'defaultYear' => (int) $request->input('jalali_year', Jalali::currentYear()),
            'defaultMonth' => (int) $request->input('jalali_month', Jalali::currentMonth()),
        ]);
    }

    /**
     * ویرایش گروهی سقف مازاد چند سمت سازمانی. مثل نسخه‌ی انواع قرارداد، ثبت برای ماه X
     * یعنی «از ماه X به بعد» (چون Position::ceilingFor آخرین نسخه تا آن ماه را می‌خواند).
     */
    public function bulkStore(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('overtime/hr-manage'), 403);

        $validated = $request->validate([
            'position_ids' => ['required', 'array', 'min:1'],
            'position_ids.*' => ['integer', 'exists:positions,id'],
            'jalali_year' => ['required', 'integer', 'min:1390', 'max:1450'],
            'jalali_month' => ['required', 'integer', 'min:1', 'max:12'],
            // هر دو اختیاری‌اند: فیلد خالی یعنی «تغییر نده» (ویرایش جزئی، مثل انواع قرارداد).
            'ceiling_per_person' => ['nullable', 'numeric', 'min:0'],
            'manager_ceiling_per_person' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (!$request->filled('ceiling_per_person') && !$request->filled('manager_ceiling_per_person')) {
            return response()->json([
                'success' => false,
                'message' => __('دست‌کم یکی از سقف‌ها را برای ویرایش گروهی وارد کنید.'),
            ], 422);
        }

        $year = (int) $validated['jalali_year'];
        $month = (int) $validated['jalali_month'];
        $positions = Position::whereIn('id', $validated['position_ids'])->get();

        DB::transaction(function () use ($positions, $request, $year, $month) {
            foreach ($positions as $position) {
                // مقدار مؤثر فعلی (ممکن است از ماه‌های قبل ارث رسیده باشد) مبنای ادغام است.
                $current = $position->ceilingFor($year, $month);

                $excess = $request->filled('ceiling_per_person')
                    ? (string) $request->input('ceiling_per_person')
                    : ($current?->ceiling_per_person !== null ? (string) $current->ceiling_per_person : '0');

                $manager = $request->filled('manager_ceiling_per_person')
                    ? (string) $request->input('manager_ceiling_per_person')
                    : ($current?->manager_ceiling_per_person !== null ? (string) $current->manager_ceiling_per_person : null);

                $position->ceilingVersions()->updateOrCreate(
                    ['jalali_year' => $year, 'jalali_month' => $month],
                    [
                        'ceiling_per_person' => $excess,
                        'manager_ceiling_per_person' => $manager,
                        'created_by' => $request->user()->id,
                    ]
                );
            }
        });

        return response()->json([
            'success' => true,
            'message' => __(':count سمت سازمانی از دوره‌ی :period به بعد به‌روزرسانی شد.', [
                'count' => $positions->count(),
                'period' => Jalali::periodLabel($year, $month),
            ]),
        ]);
    }

    public function store(PositionCeilingRequest $request, Position $position): JsonResponse
    {
        $version = $position->ceilingVersions()->updateOrCreate(
            ['jalali_year' => $request->input('jalali_year'), 'jalali_month' => $request->input('jalali_month')],
            [
                'ceiling_per_person' => $request->input('ceiling_per_person'),
                'manager_ceiling_per_person' => $request->input('manager_ceiling_per_person'),
                'created_by' => $request->user()->id,
            ]
        );

        return response()->json(['success' => true, 'message' => __('سقف سمت سازمانی ثبت شد.'), 'data' => $version]);
    }
}
