<?php

namespace App\Http\Controllers\Admin\Overtime;

use App\Http\Controllers\Controller;
use App\Models\ContractType;
use App\Models\Position;
use App\Models\Setting;
use App\Models\User;
use App\Services\PersonnelSyncService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * همگام‌سازی پرسنل از وب‌سرویس پرسنلی سازمان - فقط مدیر منابع انسانی.
 * منطق اصلی در App\Services\PersonnelSyncService است تا هم از این‌جا و هم از
 * دستور کنسول (overtime:sync-personnel) قابل استفاده باشد.
 */
class PersonnelSyncController extends Controller
{
    public function __construct(private readonly PersonnelSyncService $syncService)
    {
    }

    /** کلید ذخیره‌ی آخرین آدرس واردشده در جدول settings. */
    private const URL_SETTING_KEY = 'overtime.personnel_sync_url';

    public function index(): View
    {
        return view('admin.overtime.sync.index', [
            // آخرین آدرسی که کاربر وارد کرده، وگرنه مقدار فایل پیکربندی.
            'url' => $this->savedUrl() ?? config('services.personnel.url'),
            'lastSyncedAt' => User::whereNotNull('synced_at')->max('synced_at'),
            'counts' => [
                'personnel' => User::whereNotNull('personnel_code')->count(),
                'contract_types' => ContractType::whereNotNull('external_code')->count(),
                // سمت‌ها دیگر کد مبدأ ندارند (بر اساس عنوان یکتا می‌شوند)، پس کل رکوردها
                // شمرده می‌شود - نگاه کنید به PersonnelSyncService::resolvePosition().
                'positions' => Position::count(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['nullable', 'url'],
        ]);

        // همگام‌سازی چند صد رکورد از محدودیت پیش‌فرض ۳۰ ثانیه‌ای PHP عبور می‌کند.
        // (سرویس خودش بهینه شده - یک تراکنش و پیش‌بارگذاری - ولی با رشد تعداد پرسنل
        //  یا کندی شبکه‌ی سیستم مبدأ باز هم ممکن است طول بکشد.)
        set_time_limit((int) config('services.personnel.timeout', 30) * 10);

        $url = $validated['url'] ?? null;

        try {
            $result = $this->syncService->syncFromUrl($url);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        // فقط پس از موفقیت ذخیره می‌شود: آدرس اشتباهی که کار نکرد نباید به‌عنوان
        // مقدار پیش‌فرض دفعه‌ی بعد برگردد.
        $this->rememberUrl($url);

        return response()->json([
            'success' => true,
            'message' => __('همگام‌سازی انجام شد: :fetched رکورد دریافت شد.', ['fetched' => $result['fetched']]),
            'stats' => $result['stats'],
            'errors' => $result['errors'],
            'fetched' => $result['fetched'],
        ]);
    }

    /** آخرین آدرس ذخیره‌شده، یا null اگر هنوز چیزی ذخیره نشده باشد. */
    private function savedUrl(): ?string
    {
        $value = Setting::where('key', self::URL_SETTING_KEY)->value('value');

        return $value !== null && $value !== '' ? $value : null;
    }

    /**
     * آدرس واردشده را برای دفعه‌ی بعد نگه می‌دارد.
     *
     * آدرس خالی یعنی «از فایل پیکربندی بخوان»؛ در آن حالت رکورد ذخیره‌شده پاک
     * می‌شود تا فرم دوباره به مقدار پیکربندی برگردد - وگرنه کاربر راهی برای
     * برگشتن به حالت پیش‌فرض نداشت.
     */
    private function rememberUrl(?string $url): void
    {
        if ($url === null || $url === '') {
            // عمداً رکورد‌به‌رکورد، نه delete() روی query builder: آن حالت رویدادهای
            // Eloquent را دور می‌زند و ثبت audit را بی‌صدا از کار می‌اندازد
            // (نگاه کنید به App\Concerns\Auditable و SettingsManager::delete()).
            foreach (Setting::where('key', self::URL_SETTING_KEY)->get() as $setting) {
                $setting->delete();
            }

            return;
        }

        Setting::updateOrCreate(['key' => self::URL_SETTING_KEY], ['value' => $url]);
    }
}
