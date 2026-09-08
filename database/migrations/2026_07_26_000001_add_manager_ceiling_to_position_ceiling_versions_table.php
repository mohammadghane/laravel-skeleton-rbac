<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // بازنگری سند: «تعیین سقف اضافه کاری قابل تعریف مدیر واحد برای پرسنل
    // زیرمجموعه» حالا بر اساس **سمت سازمانی** است، نه نوع قرارداد:
    //
    //   کارمند و کارشناس: ۹۰ | رئیس گروه: ۱۱۰ | مدیر واحد: ۱۲۰ | معاونت‌ها: ۱۷۵ | شرکتی‌ها: ۷۰
    //
    // و همان بند صراحتاً می‌گوید: «تعداد پرسنل × عدد مشخص شده بر اساس سمت».
    //
    // پیش از این، سقف در اختیار مدیر از contract_type_ceiling_versions.ceiling_per_person
    // خوانده می‌شد. حالا به این ستون منتقل می‌شود.
    //
    // تفکیک دو ستون روی همین جدول:
    //   - ceiling_per_person         => سقف «مازاد» در اختیار معاونت (از قبل موجود بود)
    //   - manager_ceiling_per_person => سقف «عادی» در اختیار مدیر واحد (این مهاجرت)
    //
    // nullable است تا نسخه‌های سقفِ سمت که از قبل ثبت شده‌اند معتبر بمانند؛ اگر null باشد
    // یعنی HR هنوز سقف مدیر را برای آن سمت تعریف نکرده و ثبت اضافه‌کار عادی ممکن نیست.
    public function up(): void
    {
        Schema::table('position_ceiling_versions', function (Blueprint $table) {
            $table->decimal('manager_ceiling_per_person', 8, 2)->nullable()->after('ceiling_per_person');
        });
    }

    public function down(): void
    {
        Schema::table('position_ceiling_versions', function (Blueprint $table) {
            $table->dropColumn('manager_ceiling_per_person');
        });
    }
};
