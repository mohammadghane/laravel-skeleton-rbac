<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * شعاع مجاز هر موقعیت (بر حسب متر).
 *
 * مبنای بررسی «مجاز بودن» در منوی ثبت نظر: اگر نقطه‌ی انتخابی کاربر داخل این
 * شعاع از یکی از موقعیت‌ها باشد، مجاز است.
 *
 * پیش‌فرض ۱۰۰ متر برای رکوردهای موجود گذاشته می‌شود تا موقعیت‌های قبلی بدون
 * شعاع نمانند (شعاع صفر یعنی هیچ‌کس هرگز مجاز نیست).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->unsignedInteger('allowed_radius')->default(100)->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('allowed_radius');
        });
    }
};
