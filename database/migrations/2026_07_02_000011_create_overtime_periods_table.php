<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // رکورد اصلی گردش کار اضافه‌کار: یک ردیف به ازای هر «پرسنل + ماه شمسی».
    //
    // state machine (نگاه کنید به App\Enums\OvertimePeriodStatus و overtime_status_history
    // برای لاگ کامل انتقال‌ها):
    //   draft -> manager_submitted -> (manager_locked_at ثبت می‌شود)
    //   -> deputy_approved | deputy_rejected
    //        deputy_rejected  => برمی‌گردد به مدیر واحد برای اصلاح مجدد (manager_submitted دوباره)
    //        deputy_approved  => می‌رود نزد مدیرعامل
    //   -> ceo_approved | ceo_rejected
    //        ceo_rejected     => مستقیم برمی‌گردد نزد معاونت (نه مدیر واحد)
    //        ceo_approved     => finalized (آماده خروجی اکسل، قفل کامل)
    //
    // اسنپ‌شات‌های چارت/قرارداد/سمت: چون این‌ها ممکن است بعداً برای پرسنل تغییر کنند ولی
    // گزارش اضافه‌کار یک ماه گذشته باید وضعیت همان زمان را نشان دهد (الزام Traceability).
    public function up(): void
    {
        Schema::create('overtime_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('jalali_year');
            $table->unsignedTinyInteger('jalali_month');

            $table->string('status')->default('draft');

            $table->decimal('normal_hours', 8, 2)->default(0);       // اضافه‌کار عادی - ثبت مدیر واحد
            $table->decimal('normal_ceiling_snapshot', 8, 2)->nullable();

            $table->decimal('excess_hours', 8, 2)->default(0);       // اضافه‌کار مازاد - ثبت معاونت/مدیرعامل
            $table->decimal('excess_ceiling_snapshot', 8, 2)->nullable();

            // اسنپ‌شات چارت سازمانی و قرارداد/سمت در لحظه‌ی ایجاد رکورد
            $table->foreignId('unit_id_snap')->nullable()->constrained('units')->nullOnDelete();
            $table->string('unit_title_snap')->nullable();
            $table->foreignId('deputy_id_snap')->nullable()->constrained('deputies')->nullOnDelete();
            $table->string('deputy_title_snap')->nullable();
            $table->foreignId('position_id_snap')->nullable()->constrained('positions')->nullOnDelete();
            $table->string('position_title_snap')->nullable();
            $table->foreignId('contract_type_id_snap')->nullable()->constrained('contract_types')->nullOnDelete();
            $table->string('contract_type_title_snap')->nullable();

            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('manager_locked_at')->nullable();

            $table->foreignId('deputy_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deputy_reviewed_at')->nullable();

            $table->foreignId('ceo_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ceo_reviewed_at')->nullable();

            $table->timestamp('finalized_at')->nullable();

            $table->timestamps();

            $table->unique(['personnel_id', 'jalali_year', 'jalali_month'], 'overtime_period_personnel_month_unique');
            $table->index(['jalali_year', 'jalali_month', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_periods');
    }
};
