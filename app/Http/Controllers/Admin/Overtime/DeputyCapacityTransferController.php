<?php

namespace App\Http\Controllers\Admin\Overtime;

use App\Http\Controllers\Controller;
use App\Models\Deputy;
use App\Models\DeputyCapacityTransfer;
use App\Services\OvertimeCeilingService;
use App\Support\Jalali;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * واگذاری ظرفیت اضافه‌کار مازاد بین معاونت‌ها - تبصره‌ی اول بخش «پارامترهای وابسته به سمت
 * سازمانی» در بازنگری بند ۴.۲.
 *
 * دسترسی: مدیر منابع انسانی (دید کل سازمان) یا خودِ معاونِ واگذارکننده. معاون فقط می‌تواند
 * از ظرفیت معاونت خودش واگذار کند، نه از معاونت دیگری.
 */
class DeputyCapacityTransferController extends Controller
{
    public function __construct(private readonly OvertimeCeilingService $ceilingService)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $year = (int) $request->input('jalali_year', Jalali::currentYear());
        $month = (int) $request->input('jalali_month', Jalali::currentMonth());

        $isHr = $user->hasPermission('overtime/hr-manage');
        $ownDeputy = Deputy::where('head_user_id', $user->id)->first();

        abort_unless($isHr || $ownDeputy, 403, __('شما دسترسی مدیریت واگذاری ظرفیت ندارید.'));

        $deputies = Deputy::where('is_active', true)->orderBy('title')->get();

        // وضعیت ظرفیت هر معاونت برای همان ماه - مبنای تصمیم‌گیری کاربر.
        $capacity = $deputies->mapWithKeys(fn (Deputy $deputy) => [
            $deputy->id => [
                'base' => $this->ceilingService->deputyBaseExcessCapacity($deputy->id, $year, $month),
                'granted' => DeputyCapacityTransfer::grantedBy($deputy->id, $year, $month),
                'received' => DeputyCapacityTransfer::receivedBy($deputy->id, $year, $month),
                'remaining' => $this->ceilingService->deputyRemainingExcessCapacity($deputy->id, $year, $month),
            ],
        ]);

        $transfers = DeputyCapacityTransfer::with(['fromDeputy', 'toDeputy', 'creator'])
            ->where('jalali_year', $year)->where('jalali_month', $month)
            ->when(!$isHr, fn ($q) => $q->where(function ($q2) use ($ownDeputy) {
                $q2->where('from_deputy_id', $ownDeputy->id)->orWhere('to_deputy_id', $ownDeputy->id);
            }))
            ->latest('id')
            ->get();

        return view('admin.overtime.transfers.index', [
            'year' => $year,
            'month' => $month,
            'deputies' => $deputies,
            'capacity' => $capacity,
            'transfers' => $transfers,
            'isHr' => $isHr,
            'ownDeputy' => $ownDeputy,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'from_deputy_id' => ['required', 'integer', 'exists:deputies,id'],
            'to_deputy_id' => ['required', 'integer', 'exists:deputies,id', 'different:from_deputy_id'],
            'jalali_year' => ['required', 'integer', 'min:1390', 'max:1450'],
            'jalali_month' => ['required', 'integer', 'min:1', 'max:12'],
            'hours' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:190'],
        ], [
            'to_deputy_id.different' => __('معاونت مبدأ و مقصد نمی‌توانند یکی باشند.'),
        ]);

        // معاون فقط از ظرفیت معاونت خودش می‌تواند واگذار کند.
        if (!$user->hasPermission('overtime/hr-manage')) {
            $owns = Deputy::where('id', $validated['from_deputy_id'])->where('head_user_id', $user->id)->exists();
            abort_unless($owns, 403, __('فقط می‌توانید از ظرفیت معاونت خودتان واگذار کنید.'));
        }

        // نمی‌توان بیش از ظرفیت باقی‌مانده واگذار کرد؛ وگرنه معاونت مبدأ به تعهدی که
        // ظرفیتش را ندارد وارد می‌شود و مجموع سازمان از سقف عبور می‌کند.
        $remaining = $this->ceilingService->deputyRemainingExcessCapacity(
            (int) $validated['from_deputy_id'], (int) $validated['jalali_year'], (int) $validated['jalali_month']
        );

        if (bccomp((string) $validated['hours'], $remaining, 2) === 1) {
            return response()->json([
                'success' => false,
                'message' => __('ظرفیت باقی‌مانده‌ی این معاونت :remaining ساعت است و امکان واگذاری :hours ساعت وجود ندارد.', [
                    'remaining' => $remaining,
                    'hours' => $validated['hours'],
                ]),
            ], 422);
        }

        DeputyCapacityTransfer::create($validated + ['created_by' => $user->id]);

        return response()->json([
            'success' => true,
            'message' => __('واگذاری ظرفیت با موفقیت ثبت شد.'),
        ]);
    }

    public function destroy(Request $request, DeputyCapacityTransfer $transfer): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('overtime/hr-manage')) {
            $owns = Deputy::where('id', $transfer->from_deputy_id)->where('head_user_id', $user->id)->exists();
            abort_unless($owns, 403, __('فقط می‌توانید واگذاری‌های معاونت خودتان را حذف کنید.'));
        }

        // اگر معاونتِ گیرنده از ظرفیت دریافتی استفاده کرده باشد، پس‌گرفتن آن باعث می‌شود
        // مصرف فعلی‌اش از ظرفیت عبور کند - پس جلوی حذف گرفته می‌شود.
        $receiverRemaining = $this->ceilingService->deputyRemainingExcessCapacity(
            $transfer->to_deputy_id, $transfer->jalali_year, $transfer->jalali_month
        );

        if (bccomp((string) $transfer->hours, $receiverRemaining, 2) === 1) {
            return response()->json([
                'success' => false,
                'message' => __('معاونت گیرنده از این ظرفیت استفاده کرده است؛ ابتدا اضافه‌کار مازاد آن را کاهش دهید.'),
            ], 422);
        }

        $transfer->delete();

        return response()->json(['success' => true, 'message' => __('واگذاری حذف شد.')]);
    }
}
