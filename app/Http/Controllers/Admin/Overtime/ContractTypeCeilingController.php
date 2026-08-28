<?php

namespace App\Http\Controllers\Admin\Overtime;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Overtime\ContractTypeCeilingBulkRequest;
use App\Http\Requests\Admin\Overtime\ContractTypeCeilingRequest;
use App\Models\ContractType;
use App\Support\Jalali;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * ثبت سقف اضافه‌کار عادی + روز فعال‌سازی برای یک نوع قرارداد در یک ماه شمسی مشخص.
 * فقط مدیر منابع انسانی. هر (contract_type_id, jalali_year, jalali_month) یکتاست -
 * ثبت مجدد همان دوره یعنی ویرایش نسخه‌ی همان ماه (updateOrCreate)، نه نسخه‌ی جدید.
 */
class ContractTypeCeilingController extends Controller
{

    public function index(Request $request, ContractType $contractType): View
    {
        $versions = $contractType->ceilingVersions()->with('creator')
            ->orderByDesc('jalali_year')->orderByDesc('jalali_month')->get();

        return view('admin.overtime.ceilings.contract-type', [
            'contractType' => $contractType,
            'versions' => $versions,
            'headcount' => $contractType->personnel()->count(),
            'defaultYear' => (int) $request->input('jalali_year', Jalali::currentYear()),
            'defaultMonth' => (int) $request->input('jalali_month', Jalali::currentMonth()),
        ]);
    }

    public function store(ContractTypeCeilingRequest $request, ContractType $contractType): JsonResponse
    {
        $version = $contractType->ceilingVersions()->updateOrCreate(
            ['jalali_year' => $request->input('jalali_year'), 'jalali_month' => $request->input('jalali_month')],
            [
                // ستون NOT NULL است ولی دیگر در محاسبات نقشی ندارد - نگاه کنید به
                // توضیح ContractTypeCeilingRequest.
                'ceiling_per_person' => $request->input('ceiling_per_person', 0) ?? 0,
                'overall_ceiling' => $request->input('overall_ceiling'),
                'activation_day' => $request->input('activation_day'),
                'created_by' => $request->user()->id,
            ]
        );

        return response()->json(['success' => true, 'message' => __('سقف نوع قرارداد ثبت شد.'), 'data' => $version]);
    }

    /**
     * ویرایش گروهی: یک یا چند مقدار مشترک را برای چند نوع قرارداد هم‌زمان ثبت می‌کند.
     *
     * دو نکته‌ی مهم در رفتار:
     *
     * ۱) **ویرایش جزئی**: فقط فیلدهایی که کاربر پر کرده تغییر می‌کنند. برای بقیه، مقدار
     *    «مؤثرِ فعلی» همان نوع قرارداد (که ممکن است از نسخه‌ی ماه‌های قبل به ارث رسیده
     *    باشد) حفظ می‌شود - نه اینکه null شود. اگر مثلاً کاربر فقط روز فعال‌سازی را عوض
     *    کند، سقف‌ها دست‌نخورده می‌مانند.
     *
     * ۲) **از این ماه به بعد**: چون ContractType::ceilingFor() آخرین نسخه‌ی ثبت‌شده تا آن
     *    ماه را برمی‌گرداند، ثبت یک نسخه برای ماه X یعنی «از ماه X به بعد تا اطلاع ثانوی».
     *    نیازی به ثبت ماهانه نیست.
     */
    public function bulkStore(ContractTypeCeilingBulkRequest $request): JsonResponse
    {
        $year = (int) $request->input('jalali_year');
        $month = (int) $request->input('jalali_month');
        $clearOverall = $request->boolean('clear_overall_ceiling');

        $contractTypes = ContractType::whereIn('id', $request->input('contract_type_ids'))->get();

        $updated = [];
        $skipped = [];

        DB::transaction(function () use ($contractTypes, $request, $year, $month, $clearOverall, &$updated, &$skipped) {
            foreach ($contractTypes as $contractType) {
                // مقدار مؤثر فعلی (ممکن است از نسخه‌ی ماه‌های قبل بیاید) مبنای ادغام است.
                $current = $contractType->ceilingFor($year, $month);

                $perPerson = $request->filled('ceiling_per_person')
                    ? (string) $request->input('ceiling_per_person')
                    : ($current?->ceiling_per_person !== null ? (string) $current->ceiling_per_person : null);

                // اگر کاربر روز فعال‌سازی نداده و نوع قرارداد هم نسخه‌ی قبلی ندارد، روز ۱
                // در نظر گرفته می‌شود (یعنی پنجره‌ی ثبت از ابتدای ماه باز است). این‌طور
                // تعریفِ سقف برای یک نوع قرارداد تازه، به‌خاطر نبودِ روز فعال‌سازی بلوکه
                // نمی‌شود؛ کاربر هر وقت خواست می‌تواند روز را جداگانه تغییر دهد.
                $activationDay = $request->filled('activation_day')
                    ? (int) $request->input('activation_day')
                    : ($current?->activation_day ?? 1);

                if ($clearOverall) {
                    $overall = null;
                } elseif ($request->filled('overall_ceiling')) {
                    $overall = (string) $request->input('overall_ceiling');
                } else {
                    $overall = $current?->overall_ceiling !== null ? (string) $current->overall_ceiling : null;
                }

                // ceiling_per_person روی نوع قرارداد دیگر توسط موتور سقف استفاده نمی‌شود
                // (به سمت سازمانی منتقل شد)، ولی ستون NOT NULL است؛ صفر می‌گذاریم تا
                // ثبت «سقف کلی» یا «روز فعال‌سازی» برای نوع قراردادِ بی‌سابقه ممکن بماند.
                $perPerson ??= '0';

                $contractType->ceilingVersions()->updateOrCreate(
                    ['jalali_year' => $year, 'jalali_month' => $month],
                    [
                        'ceiling_per_person' => $perPerson,
                        'overall_ceiling' => $overall,
                        'activation_day' => $activationDay,
                        'created_by' => $request->user()->id,
                    ]
                );

                $updated[] = $contractType->title;
            }
        });

        return response()->json([
            'success' => true,
            'message' => $this->bulkMessage($updated, $skipped, $year, $month),
            'updated' => $updated,
            'skipped' => $skipped,
        ]);
    }

    private function bulkMessage(array $updated, array $skipped, int $year, int $month): string
    {
        $period = Jalali::periodLabel($year, $month);

        if ($updated === []) {
            return __('هیچ نوع قراردادی به‌روزرسانی نشد. موارد رد شده: :items', ['items' => implode('، ', $skipped)]);
        }

        $message = __(':count نوع قرارداد از دوره‌ی :period به بعد به‌روزرسانی شد.', [
            'count' => count($updated),
            'period' => $period,
        ]);

        if ($skipped !== []) {
            $message .= ' ' . __('رد شده: :items', ['items' => implode('، ', $skipped)]);
        }

        return $message;
    }
}
