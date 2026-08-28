<?php

namespace App\Http\Controllers\Admin\Overtime;

use App\Http\Controllers\Controller;
use App\Models\ContractType;
use App\Models\Position;
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

    public function index(): View
    {
        return view('admin.overtime.sync.index', [
            'url' => config('services.personnel.url'),
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

        try {
            $result = $this->syncService->syncFromUrl($validated['url'] ?? null);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('همگام‌سازی انجام شد: :fetched رکورد دریافت شد.', ['fetched' => $result['fetched']]),
            'stats' => $result['stats'],
            'errors' => $result['errors'],
            'fetched' => $result['fetched'],
        ]);
    }
}
