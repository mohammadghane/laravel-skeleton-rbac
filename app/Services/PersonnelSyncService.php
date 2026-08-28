<?php

namespace App\Services;

use App\Models\ContractType;
use App\Models\Position;
use App\Models\User;
use App\Support\PersianText;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * همگام‌سازی پرسنل، انواع قرارداد و سمت‌های سازمانی از وب‌سرویس پرسنلی سازمان.
 *
 * ساختار هر رکورد خروجی سرویس:
 *   {
 *     "EmpCode": "51228", "FirstName": "یحیی", "LastName": "رشیدی",
 *     "EmploymentTypeDesc": "رسمی", "EmploymentTypeCode": 9,
 *     "PostTitle": "مدیریت هماهنگی آب ناحیه شمال", "PostID": 1061, "PostCode": "97-7"
 *   }
 *
 * تصمیم‌های کلیدی طراحی:
 *
 * ۱) **تطبیق بر اساس کد، نه عنوان.** نوع قرارداد با EmploymentTypeCode، سمت با PostID و
 *    پرسنل با EmpCode تطبیق داده می‌شود. اگر عنوان مبنا بود، هر اصلاح نگارشی در سیستم
 *    مبدأ یک رکورد تکراری می‌ساخت.
 *
 * ۲) **نرمال‌سازی حروف عربی/فارسی.** داده‌ی مبدأ «ي» و «ك» عربی را با «ی» و «ک» فارسی
 *    قاطی کرده («يحيي» در کنار «یحیی»). بدون یکسان‌سازی، جست‌وجو و مرتب‌سازی در پنل
 *    نتیجه‌ی درست نمی‌دهد.
 *
 * ۳) **سقف‌ها و چارت دست‌نخورده می‌مانند.** این سرویس هرگز واحد/معاونت یا سقف‌های
 *    تعریف‌شده توسط HR را تغییر نمی‌دهد؛ فقط هویت پرسنل و تخصیص سمت/قرارداد را
 *    به‌روزرسانی می‌کند. تخصیص واحد و معاونت کار HR است، نه سیستم مبدأ.
 *
 * ۴) **بدون حذف.** پرسنلی که از خروجی سرویس حذف شده باشد، در این سیستم غیرفعال یا حذف
 *    نمی‌شود - چون رکوردهای اضافه‌کار تاریخی به او وابسته‌اند. صرفاً در گزارش خروجی
 *    به‌عنوان «غایب در سرویس» شمرده می‌شود تا HR خودش تصمیم بگیرد.
 */
class PersonnelSyncService
{
    /** @var array<string,int> شمارنده‌های نتیجه‌ی همگام‌سازی */
    private array $stats = [
        'contract_types_created' => 0,
        'contract_types_updated' => 0,
        'positions_created' => 0,
        'positions_updated' => 0,
        'personnel_created' => 0,
        'personnel_updated' => 0,
        'skipped' => 0,
    ];

    /** @var list<string> خطای رکوردهایی که همگام نشدند */
    private array $errors = [];

    /**
     * دریافت داده از وب‌سرویس و همگام‌سازی.
     *
     * @param  string|null  $url  اگر داده نشود از config('services.personnel.url') خوانده می‌شود
     * @return array{stats: array<string,int>, errors: list<string>, fetched: int}
     */
    public function syncFromUrl(?string $url = null): array
    {
        $url ??= config('services.personnel.url');

        if (!$url) {
            throw new \RuntimeException(__('آدرس وب‌سرویس پرسنلی تنظیم نشده است (PERSONNEL_SYNC_URL در .env).'));
        }

        $response = Http::timeout((int) config('services.personnel.timeout', 30))
            ->acceptJson()
            ->get($url);

        if (!$response->successful()) {
            throw new \RuntimeException(__('دریافت اطلاعات از وب‌سرویس ناموفق بود (کد :code).', ['code' => $response->status()]));
        }

        $records = $response->json();

        // برخی سرویس‌ها آرایه را داخل یک کلید می‌گذارند؛ هر دو حالت را می‌پذیریم.
        if (is_array($records) && !array_is_list($records)) {
            $records = collect($records)->first(fn ($v) => is_array($v)) ?? [];
        }

        if (!is_array($records)) {
            throw new \RuntimeException(__('پاسخ وب‌سرویس آرایه‌ی معتبری نیست.'));
        }

        return $this->sync($records);
    }

    /**
     * همگام‌سازی از روی آرایه‌ی رکوردها (بدون HTTP) - برای تست و ایمپورت آفلاین.
     *
     * @param  array<int,array<string,mixed>>  $records
     * @return array{stats: array<string,int>, errors: list<string>, fetched: int}
     */
    public function sync(array $records): array
    {
        // کش درون‌حافظه‌ای تا برای صدها رکورد، کوئری تکراری نزنیم.
        $contractTypes = [];
        $positions = [];

        // پیش‌بارگذاری کاربران موجود: بدون این، برای هر رکورد دو SELECT جداگانه می‌خورد
        // و روی ۳۷۸ رکورد از محدودیت ۳۰ ثانیه‌ای PHP در حالت وب عبور می‌کرد.
        $usersByCode = User::whereNotNull('personnel_code')->get()->keyBy('personnel_code');
        $usersByUsername = User::whereNull('personnel_code')->whereNotNull('username')->get()->keyBy('username');

        // یک تراکنش برای کل عملیات (نه یکی به‌ازای هر رکورد): هم بسیار سریع‌تر است و هم
        // اگر وسط کار خطای غیرمنتظره رخ دهد، دیتابیس نیمه‌همگام باقی نمی‌ماند.
        DB::transaction(function () use ($records, &$contractTypes, &$positions, $usersByCode, $usersByUsername) {
            foreach ($records as $index => $record) {
                $rowNumber = $index + 1;

                try {
                    $empCode = $this->cleanText($record['EmpCode'] ?? null);

                    if ($empCode === null) {
                        $this->errors[] = __('رکورد :row: کد پرسنلی (EmpCode) خالی است.', ['row' => $rowNumber]);
                        $this->stats['skipped']++;
                        continue;
                    }

                    $contractType = $this->resolveContractType($record, $contractTypes);
                    $position = $this->resolvePosition($record, $positions);

                    $existing = $usersByCode->get($empCode) ?? $usersByUsername->get($empCode);

                    $this->upsertPersonnel($record, $empCode, $contractType, $position, $existing);
                } catch (Throwable $e) {
                    // خطای یک رکورد کل فایل را متوقف نمی‌کند؛ گزارش می‌شود و ادامه می‌دهیم.
                    $this->errors[] = __('رکورد :row: :message', ['row' => $rowNumber, 'message' => $e->getMessage()]);
                    $this->stats['skipped']++;
                }
            }
        });

        return [
            'stats' => $this->stats,
            'errors' => $this->errors,
            'fetched' => count($records),
        ];
    }

    /** نوع قرارداد را بر اساس EmploymentTypeCode پیدا یا ایجاد می‌کند. */
    private function resolveContractType(array $record, array &$cache): ?ContractType
    {
        $code = $record['EmploymentTypeCode'] ?? null;
        $title = $this->cleanText($record['EmploymentTypeDesc'] ?? null);

        if ($code === null || $code === '') {
            return null;
        }

        $code = (int) $code;

        if (isset($cache[$code])) {
            return $cache[$code];
        }

        $existing = ContractType::where('external_code', $code)->first();

        if ($existing) {
            // عنوان اگر در مبدأ اصلاح شده باشد به‌روزرسانی می‌شود، ولی سقف‌های تعریف‌شده
            // توسط HR (که به id این رکورد وصل‌اند) دست‌نخورده باقی می‌مانند.
            if ($title !== null && $existing->title !== $title) {
                $existing->update(['title' => $title]);
                $this->stats['contract_types_updated']++;
            }

            return $cache[$code] = $existing;
        }

        // اگر نوع قراردادی با همین عنوان از قبل دستی ساخته شده باشد، به‌جای ساخت
        // رکورد تکراری، همان را به کد مبدأ متصل می‌کنیم.
        $byTitle = $title !== null ? ContractType::whereNull('external_code')->where('title', $title)->first() : null;

        if ($byTitle) {
            $byTitle->update(['external_code' => $code]);
            $this->stats['contract_types_updated']++;

            return $cache[$code] = $byTitle;
        }

        $created = ContractType::create([
            'title' => $title ?? __('نوع قرارداد :code', ['code' => $code]),
            'external_code' => $code,
            'is_active' => true,
        ]);
        $this->stats['contract_types_created']++;

        return $cache[$code] = $created;
    }

    /**
     * سمت سازمانی را بر اساس **عنوان** پیدا یا ایجاد می‌کند.
     *
     * چرا عنوان و نه PostID؟ در سیستم مبدأ، PostID شناسه‌ی «پستِ سازمانی» (جایگاه یک فرد)
     * است نه «عنوان شغلی»: داده‌ی واقعی ۲۶۳ PostID یکتا ولی فقط ۱۴۸ عنوان دارد - مثلاً
     * ۶۴ نفر با عنوان «کارشناس منابع آب» هرکدام PostID جداگانه دارند.
     *
     * چون سقف اضافه‌کار طبق بند ۴.۳ روی «تعداد پرسنل دارای آن سمت» محاسبه می‌شود، واحد
     * گروه‌بندی باید عنوان شغلی باشد؛ وگرنه هر گروه یک نفره می‌شد و HR باید یک عنوان را
     * ده‌ها بار سقف‌گذاری می‌کرد. خودِ PostID/PostCode روی رکورد پرسنل ذخیره می‌شوند.
     */
    private function resolvePosition(array $record, array &$cache): ?Position
    {
        $title = $this->cleanText($record['PostTitle'] ?? null);

        if ($title === null) {
            return null;
        }

        if (isset($cache[$title])) {
            return $cache[$title];
        }

        $existing = Position::where('title', $title)->first();

        if ($existing) {
            return $cache[$title] = $existing;
        }

        $created = Position::create(['title' => $title, 'is_active' => true]);
        $this->stats['positions_created']++;

        return $cache[$title] = $created;
    }

    /**
     * پرسنل را بر اساس EmpCode ایجاد یا به‌روزرسانی می‌کند.
     *
     * @param  User|null  $user  رکورد از پیش بارگذاری‌شده (برای پرهیز از کوئری به‌ازای هر رکورد)
     */
    private function upsertPersonnel(array $record, string $empCode, ?ContractType $contractType, ?Position $position, ?User $user = null): void
    {
        $firstName = $this->cleanText($record['FirstName'] ?? null) ?? '';
        $lastName = $this->cleanText($record['LastName'] ?? null);

        $attributes = [
            'name' => $firstName !== '' ? $firstName : $empCode,
            'family' => $lastName,
            'position_id' => $position?->id,
            'contract_type_id' => $contractType?->id,
            'personnel_code' => $empCode,
            // پستِ سازمانی (جایگاه) مشخصه‌ی خودِ فرد است، نه عنوان شغلی؛ نگاه کنید به
            // توضیح resolvePosition().
            'post_id' => isset($record['PostID']) && $record['PostID'] !== '' ? (int) $record['PostID'] : null,
            'post_code' => $this->cleanText($record['PostCode'] ?? null),
            'synced_at' => now(),
        ];

        if ($user) {
            // عمداً username/email/password/cell و تخصیص واحد و معاونت را دست نمی‌زنیم:
            // این‌ها یا اطلاعات ورود کاربرند یا تصمیم HR، و سیستم مبدأ آن‌ها را ندارد.
            $user->update($attributes);
            $this->stats['personnel_updated']++;

            return;
        }

        // ستون‌های email و password در این جدول NOT NULL هستند ولی سرویس آن‌ها را
        // نمی‌دهد؛ مقدار جایگزینِ یکتا می‌سازیم تا رکورد معتبر باشد. کاربر تا وقتی HR
        // رمز واقعی تنظیم نکند نمی‌تواند وارد شود (رمز تصادفی و دورانداختنی است).
        User::create($attributes + [
            'username' => $empCode,
            'email' => $empCode . '@' . config('services.personnel.email_domain', 'personnel.local'),
            'password' => Hash::make(Str::random(40)),
        ]);
        $this->stats['personnel_created']++;
    }

    /**
     * پاک‌سازی متن ورودی سرویس: یکسان‌سازی حروف عربی/ارقام و حذف فاصله‌ی اضافی.
     *
     * داده‌ی مبدأ «ي» و «ك» عربی را با معادل فارسی قاطی کرده و عنوان‌ها فاصله‌ی انتهایی
     * دارند (مثلاً «كارشناس برنامه و بودجه »). بدون این پاک‌سازی، دو عنوانی که برای کاربر
     * یکسان به‌نظر می‌رسند در دیتابیس متفاوت ذخیره می‌شوند.
     *
     * پیاده‌سازی در App\Support\PersianText است تا همان قواعدی که روی ورودی فرم‌ها
     * (میدل‌ور NormalizePersianInput) اعمال می‌شود، اینجا هم اعمال شود.
     */
    public static function cleanText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = PersianText::normalize((string) $value);

        return ($text === null || $text === '') ? null : $text;
    }
}
