<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // ستون‌های شناسه‌ی سیستم مبدأ (وب‌سرویس پرسنلی)، برای همگام‌سازی پایدار.
    //
    // چرا بر اساس «کد» و نه «عنوان»؟ چون عنوان‌ها در سیستم مبدأ ممکن است اصلاح شوند
    // (فاصله‌ی اضافی، تغییر نگارش، ی/ک عربی به فارسی). اگر تطبیق را روی عنوان انجام
    // دهیم، هر اصلاح جزئی یک رکورد تکراری می‌سازد. کد عددی/رشته‌ای پایدار است.
    //
    //   contract_types.external_code => EmploymentTypeCode
    //   positions.external_id        => PostID   (شناسه‌ی یکتای پست در سیستم مبدأ)
    //   positions.post_code          => PostCode (کد سازمانی پست، مثل «97-7»)
    //   users.personnel_code         => EmpCode  (کد پرسنلی)
    //
    // همه nullable هستند تا رکوردهایی که دستی در سیستم ساخته شده‌اند (بدون معادل در
    // سیستم مبدأ) معتبر بمانند.
    public function up(): void
    {
        Schema::table('contract_types', function (Blueprint $table) {
            $table->unsignedInteger('external_code')->nullable()->unique()->after('title');
        });

        Schema::table('positions', function (Blueprint $table) {
            $table->unsignedBigInteger('external_id')->nullable()->unique()->after('title');
            $table->string('post_code', 100)->nullable()->index()->after('external_id');
        });

        Schema::table('users', function (Blueprint $table) {
            // یکتا نیست چون رکوردهای قدیمی ممکن است مقدار نداشته باشند و MySQL چند NULL
            // را در ایندکس یکتا می‌پذیرد؛ ولی برای جست‌وجوی سریع ایندکس دارد.
            $table->string('personnel_code', 50)->nullable()->index()->after('username');
            $table->timestamp('synced_at')->nullable()->after('personnel_code');
        });
    }

    public function down(): void
    {
        Schema::table('contract_types', function (Blueprint $table) {
            $table->dropUnique(['external_code']);
            $table->dropColumn('external_code');
        });

        Schema::table('positions', function (Blueprint $table) {
            $table->dropUnique(['external_id']);
            $table->dropIndex(['post_code']);
            $table->dropColumn(['external_id', 'post_code']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['personnel_code']);
            $table->dropColumn(['personnel_code', 'synced_at']);
        });
    }
};
