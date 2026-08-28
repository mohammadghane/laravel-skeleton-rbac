<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // بند ۴.۲ سند، فهرست «پارامترهای مستقل»: کسر کار و ماموریت.
    // سند صراحتاً می‌گوید هر دو «فقط از طریق فایل اکسل وارد می‌شوند» - بنابراین در فرم‌های
    // ثبت اضافه‌کار ظاهر نمی‌شوند و فقط از مسیر ایمپورت اکسل توسط HR مقداردهی می‌گردند
    // (نگاه کنید به App\Imports\OvertimeIndependentParamsImport).
    //
    // مثل ضریب ریالی و روز کارکرد، این‌ها «مستقل» هستند: هیچ نقشی در محاسبه‌ی سقف ندارند و
    // صرفاً در گزارش نهایی و خروجی اکسل سیستم مالی نمایش داده می‌شوند.
    public function up(): void
    {
        Schema::table('overtime_periods', function (Blueprint $table) {
            $table->decimal('deduction_hours', 8, 2)->nullable()->after('worked_days_count'); // کسر کار
            $table->decimal('mission_hours', 8, 2)->nullable()->after('deduction_hours');     // ماموریت
        });
    }

    public function down(): void
    {
        Schema::table('overtime_periods', function (Blueprint $table) {
            $table->dropColumn(['deduction_hours', 'mission_hours']);
        });
    }
};
