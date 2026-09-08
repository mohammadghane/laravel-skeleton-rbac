<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ایمیل را nullable می‌کند.
 *
 * چرا: فیلد ایمیل از فرم پرسنل حذف شد (درخواست کارفرما)، ولی ستون NOT NULL بود.
 * بدون این مهاجرت، ساخت پرسنل جدید از فرم با خطای SQL شکست می‌خورد.
 *
 * پیش‌تر سرویس همگام‌سازی همین محدودیت را با ساختن ایمیل جعلی
 * («کدپرسنلی@personnel.local») دور می‌زد؛ آن راه‌حل هم دیگر لازم نیست.
 *
 * توجه: ورود با ایمیل همچنان برای کاربرانی که ایمیل دارند کار می‌کند
 * (LoginRequest هم username و هم email را می‌پذیرد).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        // بازگشت فقط وقتی ممکن است که هیچ رکوردی ایمیل خالی نداشته باشد.
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};
