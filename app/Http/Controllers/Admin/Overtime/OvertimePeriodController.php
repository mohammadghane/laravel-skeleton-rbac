<?php

namespace App\Http\Controllers\Admin\Overtime;

use App\Enums\OvertimePeriodStatus;
use App\Exceptions\OvertimeCeilingExceededException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Overtime\OvertimeFinancialParamsRequest;
use App\Http\Requests\Admin\Overtime\OvertimeExcessRequest;
use App\Http\Requests\Admin\Overtime\OvertimeNormalRequest;
use App\Http\Requests\Admin\Overtime\OvertimeReviewRequest;
use App\Http\Requests\Admin\Overtime\OvertimeSpecialItemLineRequest;
use App\Models\Deputy;
use App\Models\OvertimePeriod;
use App\Models\Position;
use App\Models\SpecialItem;
use App\Models\User;
use App\Services\OvertimeCeilingService;
use App\Services\OvertimePeriodService;
use App\Support\Jalali;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

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
        $ownDeputies = Deputy::where('head_user_id', $user->id)->get();
        $deputyPersonnelIds = $ownDeputies
            ->flatMap(fn (Deputy $deputy) => $deputy->allPersonnelIds())->unique()->values();
        $deputyCapacitySummary = null;
        if ($role === 'deputy') {
            $deputyCapacitySummary = [
                'total_capacity' => '0.00',
                'assigned_hours' => '0.00',
                'remaining_capacity' => '0.00',
            ];
            foreach ($ownDeputies as $deputy) {
                $summary = $this->ceilingService->deputyExcessCapacitySummary($deputy->id, $year, $month);
                foreach ($deputyCapacitySummary as $key => $value) {
                    $deputyCapacitySummary[$key] = bcadd($value, $summary[$key], 2);
                }
            }
        }

        $positions = Position::whereIn('id', $personnel->pluck('position_id')->filter()->unique())->get();
        $defaultHoursByPosition = $positions->mapWithKeys(fn (Position $position) => [
            $position->id => $position->ceilingFor($year, $month)?->manager_ceiling_per_person,
        ]);

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
            'defaultHoursByPosition' => $defaultHoursByPosition,
            'deputyPersonnelIds' => $deputyPersonnelIds,
            'deputyCapacitySummary' => $deputyCapacitySummary,
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

        // معاون و مدیر واحد: محدوده‌ی هر دو با هم.
        //
        // در داده‌ی واقعی این دو نقش لزوماً تودرتو نیستند - کسی می‌تواند معاونِ
        // معاونت الف و هم‌زمان مدیرِ واحدی زیر معاونت ب باشد. با انتخاب فقط یکی
        // از دو نقش، بخشی از پرسنلِ تحت مسئولیتش از فهرست حذف می‌شد.
        $isDeputyHead = $user->isDeputyHead();
        $isUnitManager = $user->isUnitManager();

        if ($isDeputyHead || $isUnitManager) {
            $ids = $this->registerablePersonnelIds($user);
            $labels = [];

            if ($isDeputyHead) {
                $labels = Deputy::where('head_user_id', $user->id)
                    ->pluck('title')->map(fn ($t) => __('معاونت') . ' ' . $t)->all();
            }

            if ($isUnitManager) {
                $labels = array_merge($labels, \App\Models\Unit::where('manager_user_id', $user->id)
                    ->pluck('title')->map(fn ($t) => __('واحد') . ' ' . $t)->all());
            }

            return [
                User::with($with)->whereIn('id', $ids)->orderBy('name')->get(),
                // نقش «معاون» ارجح است: اختیاراتش شامل اختیارات مدیر واحد هم هست.
                $isDeputyHead ? 'deputy' : 'manager',
                implode('، ', $labels),
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

        if ($period) {
            $this->periodService->syncPeriodSpecialItems($period);
            $period->load('specialItemLines');
        }

        $ceilingVersion = $personnel->contractType?->ceilingFor($year, $month);

        return view('admin.overtime.periods.show', [
            'personnel' => $personnel->load(['unit', 'deputy', 'position', 'contractType']),
            'period' => $period,
            'year' => $year,
            'month' => $month,
            'specialItems' => SpecialItem::where('is_active', true)
                ->whereHas('eligiblePersonnel', fn ($q) => $q->where('personnel_id', $personnel->id)->where('is_eligible', true))
                ->orderBy('title')->get(),
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
        $this->assertCanRegisterNormalFor($manager, $personnel);

        $year = (int) $request->input('jalali_year');
        $month = (int) $request->input('jalali_month');

        $this->assertRegistrationWindowOpen($personnel, $year, $month);

        $period = $this->periodService->findOrCreateDraft($personnel, $year, $month);

        abort_unless($this->canEditNormalFor($manager, $personnel, $period), 422, __('این دوره در وضعیت فعلی توسط شما قابل ویرایش نیست.'));

        try {
            $this->ceilingService->assertNormalWithinCeiling(
                $personnel, $year, $month, (string) $request->input('normal_hours'),
                excludePeriodId: $period->id
            );
        } catch (OvertimeCeilingExceededException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $period->normal_hours = $request->input('normal_hours');
        // اسنپ‌شات سقف عادی از «سمت سازمانی» گرفته می‌شود، نه نوع قرارداد.
        $period->normal_ceiling_snapshot = optional($personnel->position?->ceilingFor($year, $month))->manager_ceiling_per_person;
        $period->save();

        // ستون‌های محاسباتی از سمت سرور برمی‌گردند تا ویرایش درون‌خطیِ جدول مجبور
        // نباشد منطق سقف را در جاوااسکریپت بازنویسی کند (محاسبات bcmath است).
        $period->load('specialItemLines');

        return response()->json([
            'success' => true,
            'message' => __('اضافه‌کار عادی ثبت شد.'),
            'data' => $period,
            'computed' => [
                'total' => $period->totalHours(),
                'payable' => $period->payableHours(),
                'capped' => $period->cappedHours(),
                'exceeds' => $period->exceedsOverallCeiling(),
                'status' => $period->status->value,
                'status_label' => $period->status->label(),
                'editable' => $this->canEditNormalFor($manager, $personnel, $period),
            ],
        ]);
    }

    /**
     * ثبت گروهی اضافه‌کار عادی برای همه‌ی پرسنل زیرمجموعه‌ی مدیر واحد.
     *
     * همان قواعد ثبت تکی اعمال می‌شود: پنجره‌ی زمانی، وضعیت قابل‌ویرایش
     * و سقف گروهی. یعنی ثبت گروهی میان‌بری برای عبور از سقف نیست.
     *
     * عملیات **جزئی** است: پرسنلی که شرایطشان اجازه نمی‌دهد (دوره‌ی قفل‌شده، نبودِ سقف،
     * عبور از سقف گروهی) با ذکر دلیل گزارش می‌شوند و بقیه ثبت می‌گردند - وگرنه یک نفرِ
     * قفل‌شده کل عملیات مدیر را بلوکه می‌کرد.
     */
    public function bulkStoreNormal(Request $request): JsonResponse
    {
        $manager = $request->user();

        $validated = $request->validate([
            'normal_hours' => ['required', 'integer', 'min:0'],
            'jalali_year' => ['required', 'integer', 'min:1390', 'max:1450'],
            'jalali_month' => ['required', 'integer', 'min:1', 'max:12'],
            // اگر خالی باشد یعنی «همه‌ی زیرمجموعه»؛ در غیر این صورت فقط انتخاب‌شده‌ها.
            'personnel_ids' => ['nullable', 'array'],
            'personnel_ids.*' => ['integer'],
            // پیش‌فرض: پرسنلی که از قبل عدد دارند دست‌نخورده می‌مانند تا ثبت گروهی،
            // تنظیمات دستیِ مدیر را پاک نکند.
            'overwrite' => ['nullable', 'boolean'],
        ]);

        // مدیر واحد یا معاون؛ معاون می‌تواند برای همه‌ی واحدهای زیرمجموعه‌اش ثبت کند.
        //
        // شرط ۴۰۳ روی «نقش» است نه «خالی بودن محدوده»: مدیرِ واحدی که هنوز
        // پرسنلی ندارد باید پاسخ خالی بگیرد، نه خطای دسترسی.
        abort_unless($manager->isUnitManager() || $manager->isDeputyHead(), 403, __('شما مدیر هیچ واحد یا معاونتی نیستید.'));
        $scopeIds = $this->registerablePersonnelIds($manager);

        $year = (int) $validated['jalali_year'];
        $month = (int) $validated['jalali_month'];
        $hours = (string) $validated['normal_hours'];
        $overwrite = (bool) ($validated['overwrite'] ?? false);

        $personnel = User::whereIn('id', $scopeIds)
            ->when(!empty($validated['personnel_ids']), fn ($q) => $q->whereIn('id', $validated['personnel_ids']))
            ->with(['position', 'contractType'])
            ->get();

        $applied = 0;
        $skipped = [];
        // نتیجه‌ی هر رکوردِ موفق، تا جدول بتواند همان سطرها را بدون رفرش به‌روز کند.
        $rows = [];

        foreach ($personnel as $person) {
            $label = trim($person->name . ' ' . $person->family);

            try {
                $ceiling = $person->contractType?->ceilingFor($year, $month);

                if (!$ceiling || !$ceiling->isRegistrationWindowOpen()) {
                    $skipped[] = $label . ' — ' . __('پنجره‌ی ثبت باز نیست');
                    continue;
                }

                $period = $this->periodService->findOrCreateDraft($person, $year, $month);

                if (!$this->canEditNormalFor($manager, $person, $period)) {
                    $skipped[] = $label . ' — ' . $period->status->label();
                    continue;
                }

                // بدون overwrite، عددی که مدیر قبلاً دستی گذاشته بازنویسی نمی‌شود.
                if (!$overwrite && bccomp((string) $period->normal_hours, '0', 2) === 1) {
                    $skipped[] = $label . ' — ' . __('از قبل مقدار دارد');
                    continue;
                }

                $this->ceilingService->assertNormalWithinCeiling(
                    $person, $year, $month, $hours, excludePeriodId: $period->id
                );

                $period->normal_hours = $hours;
                $period->normal_ceiling_snapshot = optional($person->position?->ceilingFor($year, $month))->manager_ceiling_per_person;
                $period->save();
                $period->load('specialItemLines');

                $rows[$person->id] = [
                    'normal_hours' => (string) $period->normal_hours,
                    'total' => $period->totalHours(),
                    'payable' => $period->payableHours(),
                    'capped' => $period->cappedHours(),
                    'exceeds' => $period->exceedsOverallCeiling(),
                    'status' => $period->status->value,
                    'status_label' => $period->status->label(),
                    'editable' => $this->canEditNormalFor($manager, $person, $period),
                ];

                $applied++;
            } catch (OvertimeCeilingExceededException $e) {
                $skipped[] = $label . ' — ' . __('عبور از سقف گروهی');
            } catch (Throwable $e) {
                $skipped[] = $label . ' — ' . $e->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'message' => $applied > 0
                ? __('اضافه‌کار :hours ساعت برای :count نفر ثبت شد.', ['hours' => $hours, 'count' => $applied])
                : __('هیچ رکوردی ثبت نشد.'),
            'applied' => $applied,
            'skipped' => $skipped,
            'rows' => $rows,
        ]);
    }

    /** ذخیره‌ی مستقل مازاد؛ دوره در انتظار بررسی معاونت باقی می‌ماند. */
    public function storeExcess(OvertimeExcessRequest $request, OvertimePeriod $period): JsonResponse
    {
        $deputy = $request->user();
        $this->assertDeputyOwnsPersonnel($deputy, $period->personnel);
        abort_unless($period->status->allowsDeputyExcessEntry(), 422, __('ثبت اضافه‌کار مازاد فقط در وضعیت پیش‌نویس یا وضعیت‌های در اختیار معاونت امکان‌پذیر است.'));

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
        $period->save();
        $period->load('specialItemLines');

        return response()->json([
            'success' => true,
            'message' => __('اضافه‌کار مازاد ثبت شد؛ دوره همچنان برای بررسی معاونت باز است.'),
            'data' => $period,
            'capacity_summary' => $this->ceilingService->deputyExcessCapacitySummary(
                (int) $period->personnel->effectiveDeputyId(), $period->jalali_year, $period->jalali_month
            ),
            'computed' => [
                'total' => $period->totalHours(),
                'payable' => $period->payableHours(),
                'capped' => $period->cappedHours(),
                'exceeds' => $period->exceedsOverallCeiling(),
            ],
        ]);
    }

    /**
     * ارسال گروهی دوره‌های آماده به معاونت، از همان صفحه‌ی فهرست.
     *
     * چرایی وجودِ این متد در کنار submit() تکی: مدیر واحدی با ده‌ها زیرمجموعه نباید
     * مجبور باشد ردیف‌به‌ردیف ارسال کند. مثل ثبت گروهی، عملیات **جزئی** است و
     * دوره‌هایی که قابل ارسال نیستند با ذکر دلیل گزارش می‌شوند.
     *
     * نکته: پرسنلی که هنوز دوره‌ای ندارند (عددی برایشان ثبت نشده) عمداً نادیده گرفته
     * می‌شوند، نه اینکه دوره‌ی صفر برایشان ساخته و ارسال شود - ارسالِ صفر به معاونت
     * یعنی «این فرد اضافه‌کار ندارد» که تصمیمِ مدیر است، نه پیش‌فرضِ سیستم.
     */
    public function bulkSubmit(Request $request): JsonResponse
    {
        $manager = $request->user();

        $validated = $request->validate([
            'jalali_year' => ['required', 'integer', 'min:1390', 'max:1450'],
            'jalali_month' => ['required', 'integer', 'min:1', 'max:12'],
            'personnel_ids' => ['nullable', 'array'],
            'personnel_ids.*' => ['integer'],
        ]);

        abort_unless($manager->isUnitManager() || $manager->isDeputyHead(), 403, __('شما مدیر هیچ واحد یا معاونتی نیستید.'));
        $scopeIds = $this->registerablePersonnelIds($manager);

        $year = (int) $validated['jalali_year'];
        $month = (int) $validated['jalali_month'];

        $personnelIds = User::whereIn('id', $scopeIds)
            ->when(!empty($validated['personnel_ids']), fn ($q) => $q->whereIn('id', $validated['personnel_ids']))
            ->pluck('id');

        $periods = OvertimePeriod::with('personnel')
            ->whereIn('personnel_id', $personnelIds)
            ->where('jalali_year', $year)->where('jalali_month', $month)
            ->get();

        $submitted = 0;
        $skipped = [];
        $rows = [];

        foreach ($periods as $period) {
            $label = trim($period->personnel->name . ' ' . $period->personnel->family);

            if (!$period->status->editableByManager()) {
                $skipped[] = $label . ' — ' . $period->status->label();
                continue;
            }

            try {
                $period->submitted_by = $manager->id;
                $period->manager_locked_at = now();
                $period->save();

                $period->transitionTo(OvertimePeriodStatus::ManagerSubmitted, $manager->id, __('ارسال گروهی برای بررسی معاونت'));

                // پس از ارسال، سطر باید قفل شود؛ همین وضعیت را به کلاینت می‌دهیم تا
                // فیلد ویرایشِ بازِ غیرقابل‌ذخیره روی صفحه باقی نماند.
                $rows[$period->personnel_id] = [
                    'status' => $period->status->value,
                    'status_label' => $period->status->label(),
                    'editable' => $period->status->editableByManager(),
                ];

                $submitted++;
            } catch (Throwable $e) {
                $skipped[] = $label . ' — ' . $e->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'message' => $submitted > 0
                ? __(':count دوره برای بررسی معاونت ارسال شد.', ['count' => $submitted])
                : __('دوره‌ی قابل ارسالی یافت نشد.'),
            'submitted' => $submitted,
            'skipped' => $skipped,
            'rows' => $rows,
        ]);
    }

    /** مدیر واحد، دوره‌ی آماده را برای بررسی به معاونت ارسال می‌کند (قفل ویرایش برای خودش). */
    public function submit(Request $request, OvertimePeriod $period): JsonResponse
    {
        $manager = $request->user();
        // معاونی که خودش ثبت کرده باید بتواند ارسال هم بکند، وگرنه رکورد در
        // وضعیت draft گیر می‌کرد.
        $this->assertCanRegisterNormalFor($manager, $period->personnel);

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
        abort(403, __('مقدار آیتم‌های ویژه را فقط منابع انسانی تعیین می‌کند.'));
    }

    /**
     * اعمال مستقیم یک آیتم ویژه‌ی «مسئول ثبت = HR» روی دوره‌ی یک پرسنل - بدون نیاز به
     * تایید مدیر/معاون، حتی روی دوره‌ی قفل/نهایی‌شده (طبق تصمیم پروژه). اگر دوره‌ی آن ماه
     * برای این پرسنل هنوز وجود نداشته باشد، با اسنپ‌شات چارت فعلی‌اش ساخته می‌شود.
     */
    public function applyHrSpecialItem(OvertimeSpecialItemLineRequest $request, User $personnel, SpecialItem $specialItem): JsonResponse
    {
        abort_unless($request->user()->hasPermission('overtime/hr-manage'), 403);
        abort_unless($specialItem->is_active, 422, __('این آیتم غیرفعال است.'));
        abort_unless($specialItem->eligiblePersonnel()->where('personnel_id', $personnel->id)->where('is_eligible', true)->exists(), 422, __('این پرسنل مشمول این آیتم ویژه نیست.'));

        $this->periodService->applyHrSpecialItem(
            $personnel, $specialItem,
            (int) $request->input('jalali_year'), (int) $request->input('jalali_month'),
            (string) $specialItem->default_value, $request->user()->id,
        );

        return response()->json(['success' => true, 'message' => __('آیتم ویژه روی اضافه‌کار این پرسنل اعمال شد.')]);
    }

    /**
     * ثبت پارامترهای مستقل مالی (ضریب ریالی، تعداد روز کارکرد ماه) یک پرسنل برای یک ماه -
     * فقط HR، بدون تاثیر در سقف، فقط برای نمایش در گزارش نهایی و خروجی اکسل.
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

    /**
     * آیا این کاربر اجازه‌ی ثبت اضافه‌کار «عادی» برای این پرسنل را دارد؟
     *
     * دو نفر می‌توانند: مدیرِ واحدِ پرسنل، و معاونِ معاونتی که پرسنل زیرمجموعه‌اش
     * است. معاونت «در صورت لزوم» ثبت می‌کند - مثلاً وقتی واحدی مدیر ندارد یا مدیر
     * در دسترس نیست - پس دسترسی‌اش جایگزین مدیر نیست، بلکه پوششِ اوست.
     */
    private function canRegisterNormalFor(User $actor, User $personnel): bool
    {
        if ($personnel->unit && $personnel->unit->manager_user_id === $actor->id) {
            return true;
        }

        return $this->deputyOwns($actor, $personnel);
    }

    /** مدیر در وضعیت‌های خودش و معاون در پیش‌نویس/دوره‌های در اختیار معاون می‌تواند عادی را ویرایش کند. */
    private function canEditNormalFor(User $actor, User $personnel, OvertimePeriod $period): bool
    {
        if ($period->status->editableByManager() && $this->canRegisterNormalFor($actor, $personnel)) {
            return true;
        }

        return $this->deputyOwns($actor, $personnel) && $period->status->allowsDeputyExcessEntry();
    }

    /**
     * شناسه‌ی پرسنلی که این کاربر می‌تواند برایشان اضافه‌کار عادی ثبت کند.
     *
     * مدیر واحد => پرسنل واحد خودش. معاون => همه‌ی پرسنل معاونتش (واحدهای
     * زیرمجموعه + پرسنل مستقیم). اگر کاربر هر دو نقش را داشته باشد - که در
     * داده‌ی واقعی رایج است - اجتماع هر دو را می‌گیریم، وگرنه معاونی که مدیر یک
     * واحد هم هست فقط همان یک واحد را می‌دید.
     *
     * @return \Illuminate\Support\Collection<int,int>
     */
    private function registerablePersonnelIds(User $actor): \Illuminate\Support\Collection
    {
        $ids = collect();

        $unitIds = \App\Models\Unit::where('manager_user_id', $actor->id)->pluck('id');
        if ($unitIds->isNotEmpty()) {
            $ids = $ids->merge(User::whereIn('unit_id', $unitIds)->pluck('id'));
        }

        foreach (Deputy::where('head_user_id', $actor->id)->get() as $deputy) {
            $ids = $ids->merge($deputy->allPersonnelIds());
        }

        return $ids->unique()->values();
    }

    /** همان بررسی، ولی به‌جای false برگرداندن، درخواست را رد می‌کند. */
    private function assertCanRegisterNormalFor(User $actor, User $personnel): void
    {
        abort_unless(
            $this->canRegisterNormalFor($actor, $personnel),
            403,
            __('این پرسنل زیرمجموعه‌ی شما نیست.')
        );
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
