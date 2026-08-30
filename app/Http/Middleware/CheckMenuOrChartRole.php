<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * اجازه‌ی عبور اگر کاربر **یا** دسترسی منوی داده‌شده را داشته باشد، **یا** در چارت
 * سازمانی نقش اجرایی داشته باشد (مدیر واحد / معاون).
 *
 * چرا؟ مدیر واحد و معاون برای انجام وظیفه‌ی خودشان ناگزیر به «ثبت اضافه‌کار» و
 * «داشبورد» نیاز دارند. اگر این را فقط با تخصیص دستیِ دسترسی حل می‌کردیم، با هر مدیر
 * جدیدی که در چارت تعریف می‌شود یک مرحله‌ی فراموش‌شدنی اضافه می‌شد و آن مدیر بی‌آنکه
 * بداند نمی‌توانست کارش را انجام دهد.
 *
 * استفاده:  ->middleware('menu-or-chart:menu/overtime-entry')
 *           ->middleware('menu-or-chart:menu/capacity-transfers,deputy')   // فقط معاون
 */
class CheckMenuOrChartRole
{
    public function handle(Request $request, Closure $next, string $permission, string $chartRole = 'any'): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(403);
        }

        if ($user->hasPermission($permission)) {
            return $next($request);
        }

        $allowed = match ($chartRole) {
            'deputy' => $user->isDeputyHead(),
            'manager' => $user->isUnitManager(),
            default => $user->isUnitManager() || $user->isDeputyHead(),
        };

        abort_unless($allowed, 403, __('شما به این بخش دسترسی ندارید.'));

        return $next($request);
    }
}
