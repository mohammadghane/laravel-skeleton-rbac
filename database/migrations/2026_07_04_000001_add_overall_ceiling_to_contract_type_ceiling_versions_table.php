<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // بند ۴.۲ سند - «تعیین سقف کلی اضافه کار هر گروه قراردادی (برای مثال برای قرارداد رسمی
    // ۱۷۵ ساعت و قرارداد شرکتی ۱۲۰ ساعت)».
    //
    // تفاوت این ستون با ceiling_per_person که از قبل وجود داشت:
    //   - ceiling_per_person = سقفی که «مدیر واحد» می‌تواند به ازای هر نفر تعریف کند (مثال سند: ۱۴۰)
    //     و مبنای محاسبه‌ی سقف گروهی است (تعداد پرسنل × این عدد).
    //   - overall_ceiling    = سقف کلی قرارداد برای یک پرسنل (مثال سند: ۱۷۵) که مجموع
    //     «عادی + مازاد + آیتم‌های ویژه» یک فرد نباید از آن بیشتر به واحد مالی ارسال شود.
    //
    // طبق تصمیم پروژه، ساعت خام ثبت‌شده دست‌نخورده در DB می‌ماند (الزام ردیابی بند ۵) و
    // capping فقط هنگام محاسبه‌ی مجموع نهایی/خروجی مالی اعمال می‌شود - نگاه کنید به
    // OvertimePeriod::payableHours().
    //
    // nullable است تا نسخه‌های سقفِ از قبل ثبت‌شده معتبر بمانند؛ اگر null باشد هیچ
    // capping ای اعمال نمی‌شود (رفتار قبلی حفظ می‌شود).
    public function up(): void
    {
        Schema::table('contract_type_ceiling_versions', function (Blueprint $table) {
            $table->decimal('overall_ceiling', 8, 2)->nullable()->after('ceiling_per_person');
        });
    }

    public function down(): void
    {
        Schema::table('contract_type_ceiling_versions', function (Blueprint $table) {
            $table->dropColumn('overall_ceiling');
        });
    }
};
