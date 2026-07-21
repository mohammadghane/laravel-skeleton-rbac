<?php

namespace App\Http\Controllers\Admin\Overtime;

use App\Exports\OvertimeMonthlyExport;
use App\Http\Controllers\Controller;
use App\Support\Jalali;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/** خروجی اکسل ماهانه‌ی اضافه‌کار برای سیستم مالی - فقط HR (بند ۴.۷ سند). */
class OvertimeExportController extends Controller
{

    public function financial(Request $request)
    {
        $year = (int) $request->input('jalali_year', Jalali::currentYear());
        $month = (int) $request->input('jalali_month', Jalali::currentMonth());
        $onlyFinalized = $request->boolean('only_finalized', true);

        $filename = sprintf('overtime-%04d-%02d%s.xlsx', $year, $month, $onlyFinalized ? '' : '-draft');

        return Excel::download(new OvertimeMonthlyExport($year, $month, $onlyFinalized), $filename);
    }
}
