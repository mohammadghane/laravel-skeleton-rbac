<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // بخش «پارامترهای وابسته به سمت سازمانی» در بازنگری 
    //
    //   «یک معاونت در صورتی که از تمام ظرفیت اضافه کار مازاد خود استفاده نکند می‌تواند
    //    باقی‌مانده اضافه کار تا سقف معاونت خود را به معاونت دیگری واگذار کند»
    //
    // هر ردیف یک واگذاری در یک ماه شمسی مشخص است. اثر آن در موتور سقف:
    //   ظرفیت مؤثر معاونت = سقف پایه (تعداد پرسنل × سقف سمت) - واگذارشده + دریافت‌شده
    //
    // چند نکته‌ی طراحی:
    //   - به تفکیک ماه ذخیره می‌شود (نه دائمی)، چون ظرفیت هر ماه جداگانه محاسبه می‌شود.
    //   - from و to نمی‌توانند یکی باشند (در سطح سرویس چک می‌شود).
    //   - رکوردها حذف نمی‌شوند بلکه با یک ردیف معکوس/حذف صریح مدیریت می‌شوند تا
    //     ردیابی (بند ۵) حفظ شود؛ فعلاً حذف مستقیم مجاز است چون هنوز گردش تاییدی ندارد.
    public function up(): void
    {
        Schema::create('deputy_capacity_transfers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('from_deputy_id')->constrained('deputies')->cascadeOnDelete();
            $table->foreignId('to_deputy_id')->constrained('deputies')->cascadeOnDelete();

            $table->unsignedSmallInteger('jalali_year');
            $table->unsignedTinyInteger('jalali_month');

            $table->decimal('hours', 8, 2); // ساعت واگذارشده - اعشاری برای جلوگیری از خطای رند

            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['jalali_year', 'jalali_month']);
            $table->index(['from_deputy_id', 'jalali_year', 'jalali_month'], 'transfer_from_period_idx');
            $table->index(['to_deputy_id', 'jalali_year', 'jalali_month'], 'transfer_to_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deputy_capacity_transfers');
    }
};
