<?php

namespace App\Http\Controllers\Admin\Overtime;

use App\Enums\OvertimePeriodStatus;
use App\Exceptions\OvertimeCeilingExceededException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Overtime\OvertimeFinancialParamsRequest;
use App\Http\Requests\Admin\Overtime\OvertimeNormalRequest;
use App\Http\Requests\Admin\Overtime\OvertimeReviewRequest;
use App\Http\Requests\Admin\Overtime\OvertimeSpecialItemLineRequest;
use App\Models\Deputy;
use App\Models\OvertimePeriod;
use App\Models\SpecialItem;
use App\Models\User;
use App\Services\OvertimeCeilingService;
use App\Services\OvertimePeriodService;
use App\Support\Jalali;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * کنترلر اصلی گردش کار اضافه‌کار: ثبت مدیر واحد -> تایید/رد معاونت -> تایید/رد مدیرعامل.
 * مجوز هر اکشن بر اساس موقعیت واقعی کاربر در چارت سازمانی چک می‌شود (نه صرفاً Permission)،
 * چون این مجوزها per-record هستند (فقط مدیرِ همان واحد، فقط معاونِ همان معاونت).
 *
 * ساخت/یافتن رکورد Draft (با اسنپ‌شات چارت) از OvertimePeriodService عبور می‌کند تا این
 * منطق در یک‌جا باشد و بین این کنترلر و مسیر مستقل «اعمال آیتم ویژه‌ی HR» تکرار نشود.
 */
class OvertimePeriodController extends Controller
{
    public function __construct(
        private readonly OvertimeCeilingService $ceilingService,
        private readonly OvertimePeriodService $periodService,
    ) {
    }

    /** فهرست دوره‌های اضافه‌کار در محدوده‌ی دید کاربر جاری برای یک ماه شمسی مشخص. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $year = (int) $request->input('jalali_year', Jalali::currentYear());
        $month = (int) $request->input('jalali_month', Jalali::currentMonth());

        $query = OvertimePeriod::with(['personnel', 'specialItemLines'])
            ->where('jalali_year', $year)->where('jalali_month', $month);

        if ($user->hasPermission('overtime/hr-manage')) {
            // HR همه چیز را می‌بیند، بدون محدودیت اسکوپ
        } elseif ($user->hasPermission('overtime/ceo-approve')) {
            $query->whereIn('status', [OvertimePeriodStatus::DeputyApproved->value, OvertimePeriodStatus::Finalized->value]);
        } elseif ($user->isDeputyHead()) {
            $deputy = Deputy::where('head_user_id', $user->id)->firstOrFail();
            $personnelIds = $deputy->allPersonnelIds();
            $query->whereIn('personnel_id', $personnelIds);
        } elseif ($user->isUnitManager()) {
            $unit = \App\Models\Unit::where('manager_user_id', $user->id)->firstOrFail();
            $query->where('unit_id_snap', $unit->id);
        } else {
            $query->where('personnel_id', $user->id); // هر پرسنل فقط رکورد خودش را می‌بیند
        }

        return response()->json(['success' => true, 'data' => $query->get()]);
    }

    /**
     * ثبت/ویرایش اضافه‌کار عادی یک پرسنل توسط مدیر واحدش. اگر دوره وجود نداشته باشد،
     * با اسنپ‌شات چارت/قرارداد/سمت فعلی پرسنل ساخته می‌شود (فریز می‌شود برای همیشه).
     */
    public function storeNormal(OvertimeNormalRequest $request, User $personnel): JsonResponse
    {
        $manager = $request->user();
        $unit = $this->assertManagerOwnsPersonnel($manager, $personnel);

        $year = (int) $request->input('jalali_year');
        $month = (int) $request->input('jalali_month');

        $this->assertRegistrationWindowOpen($personnel, $year, $month);

        $period = $this->periodService->findOrCreateDraft($personnel, $year, $month);

        abort_if(!$period->status->editableByManager(), 422, __('این دوره در وضعیت فعلی توسط شما قابل ویرایش نیست.'));

        try {
            $this->ceilingService->assertNormalWithinCeiling(
                $personnel, $year, $month, (string) $request->input('normal_hours'),
                excludePeriodId: $period->id
            );
        } catch (OvertimeCeilingExceededException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $period->normal_hours = $request->input('normal_hours');
        $period->normal_ceiling_snapshot = optional($personnel->contractType?->ceilingFor($year, $month))->ceiling_per_person;
        $period->save();

        return response()->json(['success' => true, 'message' => __('اضافه‌کار عادی ثبت شد.'), 'data' => $period]);
    }

    /** مدیر واحد، دوره‌ی آماده را برای بررسی به معاونت ارسال می‌کند (قفل ویرایش برای خودش). */
    public function submit(Request $request, OvertimePeriod $period): JsonResponse
    {
        $manager = $request->user();
        $this->assertManagerOwnsPersonnel($manager, $period->personnel);

        abort_unless($period->status->editableByManager(), 422, __('این دوره در وضعیت فعلی قابل ارسال نیست.'));

        $period->submitted_by = $manager->id;
        $period->manager_locked_at = now();
        $period->save();

        $period->transitionTo(OvertimePeriodStatus::ManagerSubmitted, $manager->id, __('ارسال برای بررسی معاونت'));

        return response()->json(['success' => true, 'message' => __('برای بررسی معاونت ارسال شد.'), 'data' => $period->fresh()]);
    }

    /**
     * بررسی معاونت: می‌تواند اضافه‌کار مازاد را تنظیم و خطوط آیتم‌ویژه‌ی در انتظار تایید را
     * تایید کند، سپس approve/reject نهایی بزند. approve => می‌رود نزد مدیرعامل.
     * reject => مستقیم برمی‌گردد نزد مدیر واحد برای اصلاح.
     */
    public function deputyReview(OvertimeReviewRequest $request, OvertimePeriod $period): JsonResponse
    {
        $deputyHead = $request->user();
        $deputy = $this->assertDeputyOwnsPersonnel($deputyHead, $period->personnel);

        abort_unless($period->status->editableByDeputy(), 422, __('این دوره در وضعیت فعلی توسط معاونت قابل بررسی نیست.'));

        if ($request->input('decision') === 'reject') {
            $period->deputy_reviewed_by = $deputyHead->id;
            $period->deputy_reviewed_at = now();
            $period->save();
            $period->transitionTo(OvertimePeriodStatus::DeputyRejected, $deputyHead->id, $request->input('note'));

            return response()->json(['success' => true, 'message' => __('دوره رد شد و برای اصلاح نزد مدیر واحد برگشت.'), 'data' => $period->fresh()]);
        }

        // approve: تنظیم اختیاری اضافه‌کار مازاد
        if ($request->filled('excess_hours')) {
            try {
                $this->ceilingService->assertExcessWithinCeiling(
                    $period->personnel, $period->jalali_year, $period->jalali_month,
                    (string) $request->input('excess_hours'), excludePeriodId: $period->id
                );
            } catch (OvertimeCeilingExceededException $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            $period->excess_hours = $request->input('excess_hours');
            $period->excess_ceiling_snapshot = optional($period->personnel->position?->ceilingFor($period->jalali_year, $period->jalali_month))->ceiling_per_person;
        }

        // تایید خطوط آیتم ویژه‌ی در انتظار تایید معاونت (ثبت‌شده توسط مدیر واحد)
        $period->specialItemLines()->where('requires_deputy_approval', true)->update(['deputy_approved' => true]);

        $period->deputy_reviewed_by = $deputyHead->id;
        $period->deputy_reviewed_at = now();
        $period->save();

        $period->transitionTo(OvertimePeriodStatus::DeputyApproved, $deputyHead->id, $request->input('note'));

        return response()->json(['success' => true, 'message' => __('دوره تایید و برای مدیرعامل ارسال شد.'), 'data' => $period->fresh()]);
    }

    /**
     * تایید/رد نهایی مدیرعامل. مدیرعامل عددی را تغییر نمی‌دهد، فقط تایید/رد می‌کند.
     * رد => مستقیم برمی‌گردد نزد معاونت (نه نزد مدیر واحد). تایید => نهایی و قفل کامل.
     */
    public function ceoReview(OvertimeReviewRequest $request, OvertimePeriod $period): JsonResponse
    {
        abort_unless($request->user()->hasPermission('overtime/ceo-approve'), 403);
        abort_unless($period->status->editableByCeo(), 422, __('این دوره در وضعیت فعلی توسط مدیرعامل قابل بررسی نیست.'));

        $period->ceo_reviewed_by = $request->user()->id;
        $period->ceo_reviewed_at = now();
        $period->save();

        if ($request->input('decision') === 'reject') {
            $period->transitionTo(OvertimePeriodStatus::CeoRejected, $request->user()->id, $request->input('note'));

            return response()->json(['success' => true, 'message' => __('دوره توسط مدیرعامل رد شد و نزد معاونت برگشت.'), 'data' => $period->fresh()]);
        }

        $period->transitionTo(OvertimePeriodStatus::CeoApproved, $request->user()->id, $request->input('note'));
        $period->finalized_at = now();
        $period->save();
        $period->transitionTo(OvertimePeriodStatus::Finalized, $request->user()->id, __('نهایی‌سازی برای خروجی مالی'));

        return response()->json(['success' => true, 'message' => __('دوره تایید نهایی و برای سیستم مالی آماده شد.'), 'data' => $period->fresh()]);
    }

    /**
     * افزودن/ویرایش یک خط آیتم ویژه‌ی «مسئول ثبت = مدیر واحد» به یک دوره‌ی موجود.
     * فقط وقتی دوره در اختیار مدیر است (Draft/DeputyRejected) قابل ثبت است و
     * requires_deputy_approval=true می‌شود - معاونت آن را ضمن deputyReview تایید می‌کند.
     * برای آیتم‌های «مسئول ثبت = HR» از applyHrSpecialItem استفاده کنید (نیازی به دوره‌ی
     * از پیش موجود ندارد و محدودیت وضعیت هم ندارد).
     */
    public function applySpecialItem(OvertimeSpecialItemLineRequest $request, OvertimePeriod $period, SpecialItem $specialItem): JsonResponse
    {
        $manager = $request->user();

        $this->assertManagerOwnsPersonnel($manager, $period->personnel);
        abort_unless($specialItem->submitter_role === SpecialItem::SUBMITTER_UNIT_MANAGER, 403, __('ثبت این آیتم فقط در اختیار منابع انسانی است؛ از مسیر HR استفاده کنید.'));
        abort_unless($period->status->editableByManager(), 422, __('این دوره در وضعیت فعلی قابل ویرایش نیست.'));

        $line = $period->specialItemLines()->updateOrCreate(
            ['special_item_id' => $specialItem->id],
            [
                'title_snap' => $specialItem->title,
                'hours' => $request->input('hours'),
                'requires_deputy_approval' => true,
                'deputy_approved' => false,
                'entered_by' => $manager->id,
            ]
        );

        return response()->json(['success' => true, 'message' => __('آیتم ویژه ثبت شد و در انتظار تایید معاونت است.'), 'data' => $line]);
    }

    /**
     * اعمال مستقیم یک آیتم ویژه‌ی «مسئول ثبت = HR» روی دوره‌ی یک پرسنل - بدون نیاز به
     * تایید مدیر/معاون، حتی روی دوره‌ی قفل/نهایی‌شده (طبق تصمیم پروژه). اگر دوره‌ی آن ماه
     * برای این پرسنل هنوز وجود نداشته باشد، با اسنپ‌شات چارت فعلی‌اش ساخته می‌شود.
     */
    public function applyHrSpecialItem(OvertimeSpecialItemLineRequest $request, User $personnel, SpecialItem $specialItem): JsonResponse
    {
        abort_unless($request->user()->hasPermission('overtime/hr-manage'), 403);
        abort_unless($specialItem->submitter_role === SpecialItem::SUBMITTER_HR, 422, __('ثبت این آیتم در اختیار مدیر واحد است، نه HR.'));

        $this->periodService->applyHrSpecialItem(
            $personnel, $specialItem,
            (int) $request->input('jalali_year'), (int) $request->input('jalali_month'),
            (string) $request->input('hours'), $request->user()->id,
        );

        return response()->json(['success' => true, 'message' => __('آیتم ویژه روی اضافه‌کار این پرسنل اعمال شد.')]);
    }

    /**
     * ثبت پارامترهای مستقل مالی (ضریب ریالی، تعداد روز کارکرد ماه) یک پرسنل برای یک ماه -
     * فقط HR، بدون تاثیر در سقف، فقط برای نمایش در گزارش نهایی و خروجی اکسل (بند ۴.۲ سند).
     * مثل آیتم ویژه‌ی HR، حتی روی دوره‌ی نهایی‌شده هم قابل ثبت است.
     */
    public function updateFinancialParams(OvertimeFinancialParamsRequest $request, User $personnel): JsonResponse
    {
        $period = $this->periodService->findOrCreateDraft(
            $personnel, (int) $request->input('jalali_year'), (int) $request->input('jalali_month')
        );

        $period->update($request->only(['rial_coefficient', 'worked_days_count']));

        return response()->json(['success' => true, 'message' => __('پارامترهای مالی ثبت شد.'), 'data' => $period]);
    }

    private function assertManagerOwnsPersonnel(User $manager, User $personnel): \App\Models\Unit
    {
        $unit = $personnel->unit;
        abort_if(!$unit || $unit->manager_user_id !== $manager->id, 403, __('این پرسنل زیرمجموعه‌ی شما نیست.'));

        return $unit;
    }

    private function assertDeputyOwnsPersonnel(User $deputyHead, User $personnel): Deputy
    {
        $deputyId = $personnel->effectiveDeputyId();
        $deputy = $deputyId ? Deputy::find($deputyId) : null;
        abort_if(!$deputy || $deputy->head_user_id !== $deputyHead->id, 403, __('این پرسنل زیرمجموعه‌ی معاونت شما نیست.'));

        return $deputy;
    }

    private function assertRegistrationWindowOpen(User $personnel, int $year, int $month): void
    {
        $ceiling = $personnel->contractType?->ceilingFor($year, $month);
        abort_if(!$ceiling, 422, __('سقف/روز فعال‌سازی برای این نوع قرارداد در این ماه تعریف نشده است.'));
        abort_unless($ceiling->isRegistrationWindowOpen(), 422, __('پنجره‌ی ثبت اضافه‌کار برای این ماه هنوز باز نشده یا بسته شده است.'));
    }
}
