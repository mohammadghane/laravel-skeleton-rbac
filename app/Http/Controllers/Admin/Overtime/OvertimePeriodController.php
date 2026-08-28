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
use Illuminate\Contracts\View\View;
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

    /**
     * صفحه‌ی اصلی گردش کار اضافه‌کار برای ماه جاری (یا ماه انتخابی).
     *
     * برخلاف بقیه‌ی فهرست‌های پنل، اینجا «پرسنل» مبنا هستند نه رکوردهای موجود: مدیر واحد
     * باید همه‌ی زیرمجموعه‌اش را ببیند، حتی آن‌هایی که هنوز دوره‌ای برایشان ساخته نشده
     * (وگرنه امکان ثبت اولیه وجود نداشت). به همین دلیل فهرست پرسنلِ محدوده گرفته می‌شود و
     * دوره‌ی موجود - در صورت وجود - به هر پرسنل چسبانده می‌شود.
     */
    public function index(Request $request): View|JsonResponse
    {
        $user = $request->user();
        $year = (int) $request->input('jalali_year', Jalali::currentYear());
        $month = (int) $request->input('jalali_month', Jalali::currentMonth());

        [$personnel, $role, $scopeTitle] = $this->resolveWorkflowScope($user);

        $periods = OvertimePeriod::with(['specialItemLines'])
            ->where('jalali_year', $year)->where('jalali_month', $month)
            ->whereIn('personnel_id', $personnel->pluck('id'))
            ->get()
            ->keyBy('personnel_id');

        // مدیرعامل فقط مواردی را می‌بیند که به مرحله‌ی او رسیده‌اند.
        if ($role === 'ceo') {
            $allowed = [OvertimePeriodStatus::DeputyApproved->value, OvertimePeriodStatus::Finalized->value, OvertimePeriodStatus::CeoRejected->value];
            $periods = $periods->filter(fn (OvertimePeriod $p) => in_array($p->status->value, $allowed, true));
            $personnel = $personnel->filter(fn ($p) => $periods->has($p->id))->values();
        }

        // جست‌وجو روی نام/کد پرسنلی. چون فهرست پرسنلِ محدوده از قبل در حافظه است، فیلتر
        // هم روی همان مجموعه انجام می‌شود (نه کوئری جداگانه).
        if ($request->filled('q')) {
            $term = mb_strtolower((string) $request->input('q'));

            $personnel = $personnel->filter(function ($p) use ($term) {
                $haystack = mb_strtolower(trim($p->name . ' ' . $p->family) . ' ' . $p->username . ' ' . $p->personnel_code);

                return str_contains($haystack, $term);
            })->values();
        }

        // سازگاری با مصرف‌کننده‌های API که همچنان JSON انتظار دارند.
        if ($request->wantsJson() && !$request->ajax()) {
            return response()->json(['success' => true, 'data' => $periods->values()]);
        }

        return view('admin.overtime.periods.index', [
            'personnel' => $personnel,
            'periods' => $periods,
            'role' => $role,
            'scopeTitle' => $scopeTitle,
            'year' => $year,
            'month' => $month,
            'specialItems' => SpecialItem::where('is_active', true)->orderBy('title')->get(),
        ]);
    }

    /**
     * محدوده‌ی پرسنل و نقش کاربر جاری در گردش کار را برمی‌گرداند.
     *
     * @return array{0:\Illuminate\Support\Collection,1:string,2:string} [پرسنل، نقش، عنوان محدوده]
     */
    private function resolveWorkflowScope(User $user): array
    {
        $with = ['unit', 'deputy', 'position', 'contractType'];

        if ($user->isUnitManager()) {
            $unit = \App\Models\Unit::where('manager_user_id', $user->id)->firstOrFail();

            return [
                User::with($with)->where('unit_id', $unit->id)->orderBy('name')->get(),
                'manager',
                __('واحد') . ' ' . $unit->title,
            ];
        }

        if ($user->isDeputyHead()) {
            $deputy = Deputy::where('head_user_id', $user->id)->firstOrFail();

            return [
                User::with($with)->whereIn('id', $deputy->allPersonnelIds())->orderBy('name')->get(),
                'deputy',
                __('معاونت') . ' ' . $deputy->title,
            ];
        }

        if ($user->hasPermission('overtime/ceo-approve')) {
            return [User::with($with)->orderBy('name')->get(), 'ceo', __('کل سازمان')];
        }

        if ($user->hasPermission('overtime/hr-manage')) {
            return [User::with($with)->orderBy('name')->get(), 'hr', __('کل سازمان')];
        }

        // پرسنل عادی: فقط رکورد خودش را می‌بیند.
        return [User::with($with)->where('id', $user->id)->get(), 'self', __('اضافه‌کار من')];
    }

    /**
     * صفحه‌ی جزئیات و اقدام روی اضافه‌کار یک پرسنل در یک ماه.
     * همه‌ی اقدام‌های گردش کار (ثبت مدیر، بررسی معاونت، تایید مدیرعامل) از همین صفحه انجام
     * می‌شوند؛ اینکه کدام بخش نمایش داده شود بر اساس نقش کاربر و وضعیت فعلی دوره تعیین می‌گردد.
     */
    public function show(Request $request, User $personnel): View
    {
        $user = $request->user();
        $year = (int) $request->input('jalali_year', Jalali::currentYear());
        $month = (int) $request->input('jalali_month', Jalali::currentMonth());

        $this->assertCanViewPersonnel($user, $personnel);

        $period = OvertimePeriod::with(['specialItemLines', 'statusHistory.actor', 'submittedBy', 'deputyReviewedBy', 'ceoReviewedBy'])
            ->where('personnel_id', $personnel->id)
            ->where('jalali_year', $year)->where('jalali_month', $month)
            ->first();

        $ceilingVersion = $personnel->contractType?->ceilingFor($year, $month);

        return view('admin.overtime.periods.show', [
            'personnel' => $personnel->load(['unit', 'deputy', 'position', 'contractType']),
            'period' => $period,
            'year' => $year,
            'month' => $month,
            'specialItems' => SpecialItem::where('is_active', true)->orderBy('title')->get(),
            'ceilingVersion' => $ceilingVersion,
            'windowOpen' => (bool) $ceilingVersion?->isRegistrationWindowOpen(),
            'isManager' => $personnel->unit && $personnel->unit->manager_user_id === $user->id,
            'isDeputy' => $this->deputyOwns($user, $personnel),
            'isCeo' => $user->hasPermission('overtime/ceo-approve'),
            'isHr' => $user->hasPermission('overtime/hr-manage'),
        ]);
    }

    /** آیا کاربر جاری اجازه‌ی دیدن اطلاعات این پرسنل را دارد؟ */
    private function assertCanViewPersonnel(User $user, User $personnel): void
    {
        if ($user->id === $personnel->id) {
            return; // هر کسی رکورد خودش را می‌بیند
        }

        if ($user->hasPermission('overtime/hr-manage') || $user->hasPermission('overtime/ceo-approve')) {
            return;
        }

        if ($personnel->unit && $personnel->unit->manager_user_id === $user->id) {
            return;
        }

        abort_unless($this->deputyOwns($user, $personnel), 403, __('این پرسنل در محدوده‌ی دسترسی شما نیست.'));
    }

    private function deputyOwns(User $user, User $personnel): bool
    {
        $deputyId = $personnel->effectiveDeputyId();

        return $deputyId !== null && Deputy::where('id', $deputyId)->where('head_user_id', $user->id)->exists();
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
        // اسنپ‌شات سقف عادی از «سمت سازمانی» گرفته می‌شود (بازنگری بند ۴.۲)، نه نوع قرارداد.
        $period->normal_ceiling_snapshot = optional($personnel->position?->ceilingFor($year, $month))->manager_ceiling_per_person;
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
