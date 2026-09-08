<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // پارامترهای «مستقل» ذکرشده در ضریب ریالی و تعداد روز کارکرد ماه.
    // این دو در محاسبه‌ی سقف هیچ نقشی ندارند - فقط جهت نمایش در گزارش نهایی و خروجی اکسل
    // سیستم مالی هستند. مقداردهی فقط توسط HR انجام می‌شود (نگاه کنید به
    // OvertimePeriodController::updateFinancialParams) و حتی روی دوره‌ی نهایی‌شده هم قابل
    // ویرایش است، مشابه آیتم‌های ویژه‌ی HR.
    public function up(): void
    {
        Schema::table('overtime_periods', function (Blueprint $table) {
            $table->decimal('rial_coefficient', 12, 2)->nullable()->after('excess_ceiling_snapshot');
            $table->unsignedTinyInteger('worked_days_count')->nullable()->after('rial_coefficient');
        });
    }

    public function down(): void
    {
        Schema::table('overtime_periods', function (Blueprint $table) {
            $table->dropColumn(['rial_coefficient', 'worked_days_count']);
        });
    }
};
