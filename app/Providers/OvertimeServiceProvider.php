<?php

namespace App\Providers;

use App\Models\Deputy;
use App\Models\Unit;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * نقش‌های «مدیر واحد» و «معاون» به‌صورت Role در جدول roles تعریف نمی‌شوند (طبق تصمیم
 * ترکیبی پروژه) - این‌ها از موقعیت واقعی کاربر در چارت سازمانی استخراج می‌شوند.
 * این Provider همان استخراج را به شکل Gate و Blade directive در کل اپ در دسترس می‌گذارد.
 *
 * ثبت: این provider را به بوت‌استرپ اپ اضافه کنید (نگاه کنید به bootstrap/providers.snippet.php).
 */
class OvertimeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::define('overtime-unit-manager', fn ($user) => $user->isUnitManager());
        Gate::define('overtime-deputy-head', fn ($user) => $user->isDeputyHead());

        // آیا کاربر جاری مدیرِ همین واحد مشخص است؟
        Gate::define('overtime-manages-unit', fn ($user, Unit $unit) => $unit->manager_user_id === $user->id);

        // آیا کاربر جاری معاونِ همین معاونت مشخص است؟
        Gate::define('overtime-heads-deputy', fn ($user, Deputy $deputy) => $deputy->head_user_id === $user->id);

        Blade::if('unitManager', fn () => auth()->check() && auth()->user()->isUnitManager());
        Blade::if('deputyHead', fn () => auth()->check() && auth()->user()->isDeputyHead());
    }
}
