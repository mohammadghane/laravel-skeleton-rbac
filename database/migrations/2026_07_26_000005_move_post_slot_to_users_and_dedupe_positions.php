<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * اصلاح معنای «سمت سازمانی» پس از مشاهده‌ی داده‌ی واقعی وب‌سرویس.
     *
     * مشکل: در سیستم مبدأ، PostID و PostCode شناسه‌ی **پستِ سازمانی (جایگاه/صندلی)** هستند،
     * نه شناسه‌ی «عنوان شغلی». داده‌ی واقعی: ۲۶۳ PostID یکتا ولی فقط ۱۴۸ عنوان یکتا -
     * یعنی ۶۴ نفر با عنوان «کارشناس منابع آب» هرکدام PostID جداگانه دارند.
     *
     * چون نسخه‌ی قبلیِ همگام‌سازی روی PostID تطبیق می‌داد، برای هر پست یک رکورد سمت
     * جداگانه ساخته می‌شد: ۲۶۷ رکورد که ۲۵۱ تای آن‌ها فقط یک نفر داشتند. این با
     * («سقف گروه = تعداد پرسنل × سقف هر نفر») ناسازگار است - عملاً هر گروه یک نفره
     * می‌شد و HR باید یک عنوان را ده‌ها بار سقف‌گذاری می‌کرد.
     *
     * راه‌حل:
     *   - positions بر اساس **عنوان** یکتا می‌شود (واحد گروه‌بندی سقف).
     *   - PostID/PostCode به users منتقل می‌شوند، چون مشخصه‌ی «جایگاه یک فرد» هستند.
     */
    public function up(): void
    {
        // ۱) ستون‌های پست سازمانی روی users
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('post_id')->nullable()->after('position_id');
            $table->string('post_code', 100)->nullable()->after('post_id');
        });

        // ۲) انتقال مقادیر فعلی از positions به users پیش از ادغام
        DB::statement('
            UPDATE users u
            JOIN positions p ON p.id = u.position_id
            SET u.post_id = p.external_id, u.post_code = p.post_code
            WHERE p.external_id IS NOT NULL
        ');

        // ۳) ادغام سمت‌های هم‌عنوان: پرسنل به قدیمی‌ترین رکورد هر عنوان منتقل می‌شوند.
        $duplicateTitles = DB::table('positions')
            ->select('title', DB::raw('MIN(id) as keep_id'), DB::raw('COUNT(*) as total'))
            ->groupBy('title')
            ->having('total', '>', 1)
            ->get();

        foreach ($duplicateTitles as $row) {
            $removeIds = DB::table('positions')
                ->where('title', $row->title)
                ->where('id', '!=', $row->keep_id)
                ->pluck('id');

            if ($removeIds->isEmpty()) {
                continue;
            }

            DB::table('users')->whereIn('position_id', $removeIds)->update(['position_id' => $row->keep_id]);

            // اگر روی رکورد تکراری سقفی تعریف شده بود، به رکورد باقی‌مانده منتقل می‌شود
            // مگر آن‌که برای همان دوره از قبل سقفی وجود داشته باشد (تداخل کلید یکتا).
            foreach (DB::table('position_ceiling_versions')->whereIn('position_id', $removeIds)->get() as $ceiling) {
                $exists = DB::table('position_ceiling_versions')
                    ->where('position_id', $row->keep_id)
                    ->where('jalali_year', $ceiling->jalali_year)
                    ->where('jalali_month', $ceiling->jalali_month)
                    ->exists();

                if ($exists) {
                    DB::table('position_ceiling_versions')->where('id', $ceiling->id)->delete();
                } else {
                    DB::table('position_ceiling_versions')->where('id', $ceiling->id)->update(['position_id' => $row->keep_id]);
                }
            }

            DB::table('positions')->whereIn('id', $removeIds)->delete();
        }

        // ۴) external_id/post_code دیگر روی سمت معنا ندارند (به users منتقل شدند) و
        //    عنوان باید یکتا باشد تا تکرار دوباره رخ ندهد.
        Schema::table('positions', function (Blueprint $table) {
            $table->dropUnique(['external_id']);
            $table->dropIndex(['post_code']);
            $table->dropColumn(['external_id', 'post_code']);
            $table->unique('title');
        });
    }

    public function down(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->dropUnique(['title']);
            $table->unsignedBigInteger('external_id')->nullable()->after('title');
            $table->string('post_code', 100)->nullable()->after('external_id');
            $table->unique('external_id');
            $table->index('post_code');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['post_id', 'post_code']);
        });
    }
};
