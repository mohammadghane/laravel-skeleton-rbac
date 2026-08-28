<?php

use App\Http\Requests\Admin\UserRequest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // فرمت شماره موبایل از «۱۰ رقم بدون صفر» (9121111111) به «۱۱ رقم با صفر»
    // (09121111111) تغییر کرد. رکوردهای موجود باید یک‌بار نرمال شوند، وگرنه ویرایش
    // همان کاربران با خطای اعتبارسنجی مواجه می‌شد.
    public function up(): void
    {
        DB::table('users')->whereNotNull('cell')->orderBy('id')->chunkById(200, function ($users) {
            foreach ($users as $user) {
                $normalized = UserRequest::normalizeCell((string) $user->cell);

                if ($normalized !== $user->cell) {
                    DB::table('users')->where('id', $user->id)->update(['cell' => $normalized]);
                }
            }
        });
    }

    public function down(): void
    {
        // بازگشت: حذف صفر ابتدایی تا فرمت قبلی (۱۰ رقمی) برگردد.
        DB::table('users')->whereNotNull('cell')->orderBy('id')->chunkById(200, function ($users) {
            foreach ($users as $user) {
                if (strlen((string) $user->cell) === 11 && str_starts_with((string) $user->cell, '09')) {
                    DB::table('users')->where('id', $user->id)->update(['cell' => substr((string) $user->cell, 1)]);
                }
            }
        });
    }
};
