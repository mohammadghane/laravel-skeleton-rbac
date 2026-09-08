<?php

namespace App\Http\Controllers\Admin\Overtime;

use App\Http\Controllers\Controller;
use App\Imports\OvertimeHoursImport;
use App\Services\OvertimePeriodService;
use App\Support\Jalali;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

/**
 * ایمپورت اکسل پارامترهای مستقل (کسر کار / ماموریت) - سند که این دو را
 * «فقط از طریق فایل اکسل» تعریف کرده است. فقط مدیر منابع انسانی.
 *
 * هر پارامتر فرم و مسیر مستقل خودش را دارد (طبق درخواست کارفرما)، چون فایل‌های کسر کار
 * و ماموریت در عمل از دو مسیر جداگانه تولید می‌شوند.
 */
class OvertimeImportController extends Controller
{
    public function __construct(private readonly OvertimePeriodService $periodService)
    {
    }

    /** صفحه‌ی بارگذاری - شامل هر دو فرم مجزا. */
    public function form(Request $request): View
    {
        return view('admin.overtime.import.index', [
            'year' => (int) $request->input('jalali_year', Jalali::currentYear()),
            'month' => (int) $request->input('jalali_month', Jalali::currentMonth()),
        ]);
    }

    /** ایمپورت فایل «کسر کار». */
    public function deduction(Request $request): JsonResponse
    {
        return $this->handle($request, OvertimeHoursImport::TYPE_DEDUCTION);
    }

    /** ایمپورت فایل «ماموریت». */
    public function mission(Request $request): JsonResponse
    {
        return $this->handle($request, OvertimeHoursImport::TYPE_MISSION);
    }

    private function handle(Request $request, string $type): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
            'jalali_year' => ['nullable', 'integer', 'min:1390', 'max:1450'],
            'jalali_month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $import = new OvertimeHoursImport(
            $type,
            (int) $request->input('jalali_year', Jalali::currentYear()),
            (int) $request->input('jalali_month', Jalali::currentMonth()),
            $this->periodService,
        );

        Excel::import($import, $request->file('file'));

        return response()->json([
            'success' => true,
            'message' => __(':count ردیف :label با موفقیت ثبت شد.', [
                'count' => $import->importedCount,
                'label' => $import->label(),
            ]),
            'imported' => $import->importedCount,
            'errors' => $import->errors,
        ]);
    }
}
