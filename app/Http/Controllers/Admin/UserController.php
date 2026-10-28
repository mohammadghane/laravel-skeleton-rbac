<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BulkDeletes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\AuditLog;
use App\Models\Deputy;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserController extends Controller
{
    use BulkDeletes;

    public function index(Request $request): View
    {
        $currentUser = $request->user();

        $users = User::query()
            // position برای ستون «سمت سازمانی» در جدول - بدون eager load، به‌ازای هر
            // ردیف یک کوئری اضافه می‌خورد (N+1).
            //
            // deputy و unit.deputy هر دو لازم‌اند: معاونتِ اکثر پرسنل از طریق واحدشان
            // مشخص می‌شود نه ستون deputy_id (نگاه کنید به User::effectiveDeputy()).
            ->with(['position', 'deputy', 'unit.deputy'])
            // حساب سرویسیِ `admin` پرسنل سازمان نیست و نباید در این فهرست دیده شود
            // (و در نتیجه در حذف گروهی هم انتخاب نمی‌شود).
            //
            // نکته: `username != 'admin'` به‌تنهایی کافی نیست، چون در SQL مقایسه با NULL
            // نتیجه‌ی NULL می‌دهد و رکوردهای بدون نام کاربری هم از فهرست حذف می‌شدند.
            ->where(fn ($q) => $q->whereNull('username')->orWhere('username', '!=', 'admin'))
            ->when($currentUser->is_agency, fn ($q) => $q->where('agency_id', $currentUser->id))
            ->when(!$currentUser->isAdmin(), fn ($q) => $q->where('center_id', $currentUser->center_id))
            ->when($request->filled('filters.gender'), fn ($q) => $q->where('gender', $request->input('filters.gender')))
            ->when($request->filled('filters.unit_id'), fn ($q) => $q->where('unit_id', $request->input('filters.unit_id')))
            ->when($request->filled('filters.deputy_id'), function ($q) use ($request) {
                $deputyId = $request->input('filters.deputy_id');
                $q->where(function ($scope) use ($deputyId) {
                    $scope->where('deputy_id', $deputyId)
                        ->orWhereHas('unit', fn ($unit) => $unit->where('deputy_id', $deputyId));
                });
            })
            // جست‌وجوی آزاد روی نام، نام خانوادگی، نام کاربری، کد پرسنلی، موبایل و کد ملی.
            // ورودی پیش‌تر توسط میدل‌ور NormalizePersianInput یکسان شده، پس تایپ با کیبورد
            // عربی یا ارقام فارسی هم نتیجه می‌دهد.
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%' . $request->input('q') . '%';

                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                        ->orWhere('family', 'like', $term)
                        ->orWhere('username', 'like', $term)
                        ->orWhere('personnel_code', 'like', $term)
                        ->orWhere('cell', 'like', $term)
                        ->orWhere('national_code', 'like', $term)
                        ->orWhereRaw("CONCAT(name, ' ', COALESCE(family, '')) LIKE ?", [$term])
                        // سمت سازمانی هم ستونی از همین جدول است، پس کاربر انتظار دارد
                        // جست‌وجوی «کارشناس» پرسنل آن سمت را برگرداند.
                        ->orWhereHas('position', fn ($p) => $p->where('title', 'like', $term))
                        // معاونت هم ستون نمایشی است؛ هر دو مسیر (مستقیم و از طریق واحد)
                        // باید جست‌وجو شوند، وگرنه جست‌وجو برای تقریباً همه‌ی پرسنل
                        // نتیجه‌ای برنمی‌گرداند.
                        ->orWhereHas('deputy', fn ($d) => $d->where('title', 'like', $term))
                        ->orWhereHas('unit.deputy', fn ($d) => $d->where('title', 'like', $term));
                });
            })
            ->orderByDesc('id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        $perPageOptions = self::PER_PAGE_OPTIONS;
        $perPage = $this->perPage($request);
        $deputies = Deputy::orderBy('title')->get(['id', 'title']);
        $units = Unit::orderBy('title')->get(['id', 'title']);

        if ($request->ajax() && $request->boolean('rows_only')) {
            return view('admin.users.partials.table', compact('users', 'perPageOptions', 'perPage'));
        }

        return view('admin.users.index', compact('users', 'perPageOptions', 'perPage', 'deputies', 'units'));
    }

    /** گزینه‌های مجاز «تعداد در هر صفحه». */
    public const PER_PAGE_OPTIONS = [20, 50, 100, 200];

    /**
     * تعداد رکورد در هر صفحه بر اساس انتخاب کاربر.
     *
     * مقدار فقط از فهرست مجاز پذیرفته می‌شود؛ در غیر این صورت یک درخواست دستکاری‌شده مثل
     * ?per_page=1000000 می‌توانست کل جدول را در حافظه بارگذاری کند.
     */
    private function perPage(Request $request): int
    {
        $requested = (int) $request->input('per_page');

        return in_array($requested, self::PER_PAGE_OPTIONS, true)
            ? $requested
            : (int) config('app.page_size', 20);
    }

    public function profile(Request $request): View
    {
        $user = $request->user()->load('roles');
        $data = [
            'user' => $user,
            'roles' => Role::all(),
            // فرم مشترک است؛ نبودِ این کلید باعث می‌شد فهرست نقش‌ها خالی رندر شود.
            'currentRoleIds' => $user->roles->pluck('id')->all(),
            'editingSelf' => true,
            'units' => \App\Models\Unit::with('deputy')->orderBy('title')->get(),
            'deputies' => \App\Models\Deputy::orderBy('title')->get(),
            'positions' => \App\Models\Position::orderBy('title')->get(),
            'contractTypes' => \App\Models\ContractType::orderBy('title')->get(),
        ];

        if ($request->ajax()) {
            return view('admin.users.form', $data);
        }

        return view('admin.modal-page', [
            'title' => __('پروفایل کاربری'),
            'view' => 'admin.users.form',
            'data' => $data,
        ]);
    }

    /**
     * Ajax username/email/cell/national_code uniqueness probe.
     * Old route: UserController::isValid()
     */
    public function checkUnique(Request $request): JsonResponse
    {
        $field = $request->input('field'); // one of: username, email, cell, national_code
        $value = $request->input('value');
        $ignoreId = $request->input('id');

        if (!in_array($field, ['username', 'email', 'cell', 'national_code'], true) || $value === null) {
            return response()->json(['valid' => false]);
        }

        $exists = User::where($field, $value)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        return response()->json(['valid' => !$exists]);
    }

    public function edit(?User $user, Request $request): View
    {
        $currentUser = $request->user();

        if ($user && $currentUser->is_agency && $user->agency_id !== $currentUser->id) {
            abort(404);
        }

        // حساب سرویسیِ admin از فهرست پرسنل پنهان است؛ ویرایش مستقیم آن هم نباید ممکن
        // باشد، وگرنه پنهان‌سازی صرفاً ظاهری می‌شد.
        abort_if($user && $user->username === 'admin' && $currentUser->username !== 'admin', 404);

        $data = [
            'user' => $user?->load('roles'),
            'roles' => Role::all(),
            'currentRoleIds' => $user?->roles->pluck('id')->all() ?? [],
            // فیلدهای چارت سازمانی برای ماژول اضافه‌کار
            'units' => \App\Models\Unit::with('deputy')->orderBy('title')->get(),
            'deputies' => \App\Models\Deputy::orderBy('title')->get(),
            'positions' => \App\Models\Position::orderBy('title')->get(),
            'contractTypes' => \App\Models\ContractType::orderBy('title')->get(),
        ];

        if ($request->ajax()) {
            return view('admin.users.form', $data);
        }

        return view('admin.modal-page', [
            'title' => $user ? __('ویرایش پرسنل') : __('پرسنل جدید'),
            'view' => 'admin.users.form',
            'data' => $data,
        ]);
    }

    public function store(UserRequest $request): JsonResponse
    {
        $currentUser = $request->user();
        $data = $request->validated();

        // Non-admins may only ever edit themselves (mirrors old `!isAllow('system-manager')` guard).
        if (!$currentUser->hasPermission('admin/users')) {
            $data['id'] = $currentUser->id;
        }

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);

            // ستون password در جدول NOT NULL است. برای کاربر جدیدی که رمز برایش
            // تعیین نشده، رمز تصادفیِ دورانداختنی می‌گذاریم تا ثبت با خطای SQL
            // شکست نخورد. کاربر تا وقتی رمز واقعی تنظیم نشود نمی‌تواند وارد شود
            // (همان الگوی PersonnelSyncService برای پرسنلِ آمده از وب‌سرویس).
            if (empty($data['id'])) {
                $data['password'] = Hash::make(Str::random(40));
            }
        }

        if ($currentUser->is_agency) {
            if (!empty($data['id'])) {
                $owned = User::where('id', $data['id'])->where('agency_id', $currentUser->id)->exists();
                abort_unless($owned, 404);
            }
            $data['agency_id'] = $currentUser->id;
        }

        // ویرایش پروفایل خود کاربر، هرگز نقش‌ها را تغییر نمی‌دهد.
        //
        // فرم پروفایل عمداً فیلد «نقش سازمانی» را نشان نمی‌دهد (کاربر نباید نقش
        // خودش را عوض کند)، پس roles[] اصلاً ارسال نمی‌شود. پیش‌تر این «نبودِ
        // فیلد» با «کاربر هیچ نقشی انتخاب نکرده» یکی گرفته می‌شد و نقش پیش‌فرض
        // user جایگزین می‌گشت - یعنی مدیر سیستم با عوض‌کردن رمز خودش، همه‌ی
        // دسترسی‌هایش را از دست می‌داد.
        //
        // بررسی بر اساس مسیر است نه صرفاً برابری شناسه: مدیری که از فرم کاربران
        // پروفایل خودش را ویرایش می‌کند همچنان باید بتواند نقش‌ها را تغییر دهد.
        $isProfileRoute = $request->routeIs('admin.profile.store');

        if ($isProfileRoute || !$currentUser->hasPermission('admin/users')) {
            // null یعنی «به نقش‌ها دست نزن». حتی اگر کسی دستی roles[] بفرستد،
            // اینجا نادیده گرفته می‌شود تا ارتقای دسترسی ممکن نباشد.
            $roles = null;
        } else {
            // در فرم کاربران، نبودِ roles یعنی مدیر همه را برداشته است؛ آنجا
            // نقش پیش‌فرض معنا دارد.
            $roles = $data['roles'] ?? [Role::where('name', 'user')->value('id')];
            $roles = array_values(array_filter($roles));
        }

        unset($data['roles']);

        $isNew = empty($data['id']);
        if ($isNew) {
            $data['setter'] = $currentUser->id;
            $data['set_with'] = $request->ip();
        }

        $user = User::updateOrCreate(['id' => $data['id'] ?? null], $data);

        if ($isNew) {
            $user->sana_code = random_int(101, 999) . 'C' . $user->id;
            $user->save();
        }

        $before = $isNew ? [] : $user->roles()->pluck('name')->sort()->values()->all();

        if ($roles !== null) {
            $user->roles()->sync($roles);
            User::forgetPermissionCache($user->id);

            $after = Role::whereIn('id', $roles)->pluck('name')->sort()->values()->all();
            if ($before !== $after) {
                AuditLog::record('updated', $user, [
                    'roles' => ['old' => $before, 'new' => $after],
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => __('اطلاعات کاربر با موفقیت به ثبت رسید'),
        ]);
    }

    public function destroy(User $user, Request $request): JsonResponse
    {
        $currentUser = $request->user();
        if ($currentUser->is_agency && $user->agency_id !== $currentUser->id) {
            abort(404);
        }

        abort_if($user->username === 'admin', 404);

        $user->delete(); // soft delete

        return response()->json(['success' => true]);
    }

    /**
     * حذف گروهی پرسنل (soft delete).
     *
     * دو محافظ: کاربر نمی‌تواند خودش را حذف کند (وگرنه بلافاصله دسترسی‌اش را از دست
     * می‌دهد)، و پرسنلی که مدیر واحد یا معاون است حذف نمی‌شود - چون گردش تایید اضافه‌کار
     * آن واحد/معاونت بی‌مسئول می‌ماند.
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $currentUser = $request->user();

        return $this->bulkDelete(
            $request,
            User::class,
            function (User $user) use ($currentUser) {
                if ($user->id === $currentUser->id) {
                    return __('حساب خودتان');
                }

                if ($user->username === 'admin') {
                    return __('حساب سرویسی سیستم');
                }

                if ($currentUser->is_agency && $user->agency_id !== $currentUser->id) {
                    return __('خارج از محدوده‌ی دسترسی شما');
                }

                if (\App\Models\Unit::where('manager_user_id', $user->id)->exists()) {
                    return __('مدیر یک واحد است');
                }

                if (\App\Models\Deputy::where('head_user_id', $user->id)->exists()) {
                    return __('معاون یک معاونت است');
                }

                return null;
            },
            __('پرسنل'),
        );
    }
}
