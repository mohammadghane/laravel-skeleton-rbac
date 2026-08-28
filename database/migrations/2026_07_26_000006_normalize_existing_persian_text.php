<?php

use App\Support\PersianText;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * یکسان‌سازی یک‌باره‌ی داده‌های موجود: «ي/ك» عربی به فارسی و ارقام فارسی/عربی به لاتین.
     *
     * از این پس ورودی‌های جدید توسط میدل‌ور NormalizePersianInput و سرویس همگام‌سازی
     * یکسان می‌شوند، ولی رکوردهایی که پیش‌تر ذخیره شده‌اند باید یک‌بار اصلاح شوند - وگرنه
     * جست‌وجوی «کارشناس» (با ک فارسی) رکورد «كارشناس» (با ك عربی) را پیدا نمی‌کند.
     *
     * توجه: عناوین سمت پس از یکسان‌سازی ممکن است با هم یکی شوند و ایندکس یکتای
     * positions.title را نقض کنند؛ در آن حالت رکوردها ادغام می‌شوند (پرسنل و سقف‌ها به
     * رکورد باقی‌مانده منتقل می‌شوند)، دقیقاً مثل مهاجرت حذف تکراری‌ها.
     */
    public function up(): void
    {
        $this->normalizeSimpleTitles('contract_types');
        $this->normalizeSimpleTitles('units');
        $this->normalizeSimpleTitles('deputies');
        $this->normalizeSimpleTitles('special_items');

        $this->normalizePositions();
        $this->normalizeUsers();
    }

    public function down(): void
    {
        // بازگشت‌پذیر نیست: شکل اصلیِ هر رکورد (عربی یا فارسی) پس از یکسان‌سازی قابل
        // بازیابی نیست. این عملیات هم بی‌خطر است و نیازی به بازگشت ندارد.
    }

    /** جدول‌هایی که فقط ستون title دارند و یکتایی عنوان برایشان الزامی نیست. */
    private function normalizeSimpleTitles(string $table): void
    {
        foreach (DB::table($table)->select('id', 'title')->get() as $row) {
            $clean = PersianText::normalize($row->title);

            if ($clean !== null && $clean !== $row->title) {
                DB::table($table)->where('id', $row->id)->update(['title' => $clean]);
            }
        }
    }

    /** سمت‌ها: عنوان یکتاست، پس یکسان‌سازی ممکن است باعث ادغام دو رکورد شود. */
    private function normalizePositions(): void
    {
        foreach (DB::table('positions')->select('id', 'title')->orderBy('id')->get() as $row) {
            $clean = PersianText::normalize($row->title);

            if ($clean === null || $clean === $row->title) {
                continue;
            }

            $conflict = DB::table('positions')->where('title', $clean)->where('id', '!=', $row->id)->first();

            if (!$conflict) {
                DB::table('positions')->where('id', $row->id)->update(['title' => $clean]);
                continue;
            }

            // رکورد هم‌عنوان از قبل هست => این یکی ادغام می‌شود.
            DB::table('users')->where('position_id', $row->id)->update(['position_id' => $conflict->id]);

            foreach (DB::table('position_ceiling_versions')->where('position_id', $row->id)->get() as $ceiling) {
                $exists = DB::table('position_ceiling_versions')
                    ->where('position_id', $conflict->id)
                    ->where('jalali_year', $ceiling->jalali_year)
                    ->where('jalali_month', $ceiling->jalali_month)
                    ->exists();

                $exists
                    ? DB::table('position_ceiling_versions')->where('id', $ceiling->id)->delete()
                    : DB::table('position_ceiling_versions')->where('id', $ceiling->id)->update(['position_id' => $conflict->id]);
            }

            DB::table('positions')->where('id', $row->id)->delete();
        }
    }

    /** پرسنل: نام، نام خانوادگی و کدها. ایمیل/رمز عبور عمداً دست نمی‌خورند. */
    private function normalizeUsers(): void
    {
        $columns = ['name', 'family', 'username', 'personnel_code', 'post_code', 'national_code', 'cell'];

        DB::table('users')->select(array_merge(['id'], $columns))->orderBy('id')->chunkById(200, function ($users) use ($columns) {
            foreach ($users as $user) {
                $changes = [];

                foreach ($columns as $column) {
                    if ($user->$column === null) {
                        continue;
                    }

                    $clean = PersianText::normalize((string) $user->$column);

                    if ($clean !== null && $clean !== $user->$column) {
                        $changes[$column] = $clean;
                    }
                }

                if ($changes !== []) {
                    DB::table('users')->where('id', $user->id)->update($changes);
                }
            }
        });
    }
};
