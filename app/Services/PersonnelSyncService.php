<?php

namespace App\Services;

use App\Models\ContractType;
use App\Models\Deputy;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use App\Support\PersianText;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * همگام‌سازی پرسنل و چارت سازمانی از وب‌سرویس پرسنلی سازمان.
 *
 * ساختار هر رکورد خروجی سرویس:
 *   {
 *     "PersonnelCode": 52308, "FirstName": "حمیدرضا", "LastName": "رکنی",
 *     "PostType": "5",        "PostName": "کارشناس",
 *     "DepartmentType": "2",  "DepartmentName": "معاونت حفاظت و بهره برداری",
 *     "EmploymentType": "1",  "EmploymentName": "رسمی-پیمانی",
 *     "ManagerPersonnelCode": 52155
 *   }
 *
 * نگاشت:
 *   PersonnelCode  => users.personnel_code
 *   PostType/Name  => positions (سمت سازمانی، مبنای سقف اضافه‌کار)
 *   EmploymentType => contract_types (نوع قرارداد)
 *   DepartmentType => deputies (معاونت)
 *   ManagerPersonnelCode => units (واحد، بر اساس مدیر مستقیم هر فرد)
 *
 * تصمیم‌های کلیدی طراحی:
 *
 * ۱) **تطبیق بر اساس کد، نه عنوان.** اگر عنوان مبنا بود، هر اصلاح نگارشی در سیستم مبدأ
 *    یک رکورد تکراری می‌ساخت و سقف‌های تعریف‌شده‌ی HR روی رکورد قدیمی بی‌استفاده می‌ماند.
 *
 * ۲) **چارت سازمانی از زنجیره‌ی مدیریتی ساخته می‌شود.** هر کسی که زیردست دارد، مدیرِ یک
 *    واحد می‌شود؛ چون بند ۴.۴ سند ثبت اضافه‌کار را وظیفه‌ی «مدیر واحد» می‌داند و بدون
 *    این نگاشت، هیچ‌کس نمی‌توانست برای پرسنل اضافه‌کار ثبت کند.
 *
 *    توجه: DepartmentName جایگاهِ **نشستن** فرد است نه چیزی که مدیریت می‌کند - چهار معاون
 *    سازمان همگی DepartmentName = «رئیس هیئت مدیره و مدیرعامل» دارند. بنابراین معاونتِ
 *    هر فرد از DepartmentType خودش خوانده می‌شود، ولی معاونِ هر معاونت از روی PostType=2
 *    و زنجیره‌ی مدیریتی پیدا می‌گردد.
 *
 * ۳) **سقف‌ها دست‌نخورده می‌مانند.** این سرویس هرگز سقف‌های تعریف‌شده توسط HR را تغییر
 *    نمی‌دهد؛ فقط هویت پرسنل و جایگاه سازمانی را به‌روزرسانی می‌کند.
 *
 * ۴) **بدون حذف.** پرسنلی که از خروجی سرویس حذف شده باشد، غیرفعال یا حذف نمی‌شود - چون
 *    رکوردهای اضافه‌کار تاریخی به او وابسته‌اند.
 */
class PersonnelSyncService
{
    /** @var array<string,int> شمارنده‌های نتیجه‌ی همگام‌سازی */
    private array $stats = [
        'contract_types_created' => 0,
        'contract_types_updated' => 0,
        'positions_created' => 0,
        'positions_updated' => 0,
        'deputies_created' => 0,
        'units_created' => 0,
        'personnel_created' => 0,
        'personnel_updated' => 0,
        'skipped' => 0,
    ];

    /** @var list<string> خطای رکوردهایی که همگام نشدند */
    private array $errors = [];

    /**
     * دریافت داده از وب‌سرویس و همگام‌سازی.
     *
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
        // نمایه‌ی کد پرسنلی => رکورد، برای پیمایش زنجیره‌ی مدیریتی.
        $byCode = [];
        foreach ($records as $record) {
            $code = $this->cleanText($record['PersonnelCode'] ?? null);

            if ($code !== null) {
                $byCode[$code] = $record;
            }
        }

        // کش درون‌حافظه‌ای تا برای صدها رکورد، کوئری تکراری نزنیم.
        $contractTypes = [];
        $positions = [];
        $deputies = [];
        $units = [];

        // پیش‌بارگذاری کاربران موجود: بدون این، برای هر رکورد دو SELECT جداگانه می‌خورد
        // و روی صدها رکورد از محدودیت زمانی PHP در حالت وب عبور می‌کرد.
        $usersByCode = User::whereNotNull('personnel_code')->get()->keyBy(
            fn (User $user) => (string) $user->personnel_code
        );
        $usersByUsername = User::whereNull('personnel_code')->whereNotNull('username')->get()->keyBy('username');

        DB::transaction(function () use (
            $records, $byCode, &$contractTypes, &$positions, &$deputies, &$units, $usersByCode, $usersByUsername
        ) {
            // مرحله‌ی ۱: ساخت پرسنل، سمت‌ها، قراردادها و معاونت‌ها.
            foreach ($records as $index => $record) {
                $rowNumber = $index + 1;

                try {
                    $code = $this->cleanText($record['PersonnelCode'] ?? null);

                    if ($code === null) {
                        $this->errors[] = __('رکورد :row: کد پرسنلی خالی است.', ['row' => $rowNumber]);
                        $this->stats['skipped']++;
                        continue;
                    }

                    $contractType = $this->resolveContractType($record, $contractTypes);
                    $position = $this->resolvePosition($record, $positions);
                    $deputy = $this->resolveDeputy($record, $deputies);

                    $existing = $usersByCode->get($code) ?? $usersByUsername->get($code);

                    $this->upsertPersonnel($record, $code, $contractType, $position, $deputy, $existing);
                } catch (Throwable $e) {
                    // خطای یک رکورد کل عملیات را متوقف نمی‌کند.
                    $this->errors[] = __('رکورد :row: :message', ['row' => $rowNumber, 'message' => $e->getMessage()]);
                    $this->stats['skipped']++;
                }
            }

            // مرحله‌ی ۲: چارت سازمانی - باید پس از ساخت همه‌ی پرسنل انجام شود، چون
            // مدیرِ یک فرد ممکن است در رکوردهای بعدیِ همین فایل آمده باشد.
            $this->buildOrgChart($records, $byCode, $units, $deputies);
        });

        return [
            'stats' => $this->stats,
            'errors' => $this->errors,
            'fetched' => count($records),
        ];
    }

    /**
     * ساخت واحدها و اتصال پرسنل به مدیرشان + تعیین معاونِ هر معاونت.
     *
     * @param  array<string,array>  $byCode  نمایه‌ی کد پرسنلی => رکورد مبدأ
     */
    private function buildOrgChart(array $records, array $byCode, array &$units, array &$deputies): void
    {
        // نکته: keyBy روی ستون عددیِ personnel_code کلیدهای int می‌سازد، ولی جست‌وجو با
        // رشته‌ی خروجی cleanText انجام می‌شود و Collection::get نوع‌حساس است. بدون این
        // cast، همه‌ی جست‌وجوها بی‌صدا null برمی‌گرداندند و چارت ساخته نمی‌شد.
        $usersByCode = User::whereNotNull('personnel_code')->get()->keyBy(
            fn (User $user) => (string) $user->personnel_code
        );

        // معاونِ هر معاونت: کسی که PostType=2 دارد و زیرمجموعه‌اش در آن معاونت است.
        // (خودِ معاون در DepartmentType دفتر مدیرعامل ثبت شده، پس از روی زیردستانش
        //  تشخیص داده می‌شود نه از DepartmentType خودش.)
        foreach ($records as $record) {
            $managerCode = $this->cleanText($record['ManagerPersonnelCode'] ?? null);
            $manager = $managerCode !== null ? ($byCode[$managerCode] ?? null) : null;

            if (!$manager || ($manager['PostType'] ?? null) !== '2') {
                continue;
            }

            $deputy = $this->resolveDeputy($record, $deputies);
            $managerUser = $usersByCode->get($managerCode);

            if ($deputy && $managerUser && $deputy->head_user_id !== $managerUser->id) {
                $deputy->update(['head_user_id' => $managerUser->id]);
            }
        }

        // واحدها: هر کسی که زیردست دارد، مدیرِ یک واحد می‌شود. بدون این، پرسنل بدون
        // مدیر می‌مانند و هیچ‌کس نمی‌تواند برایشان اضافه‌کار ثبت کند (بند ۴.۴).
        $subordinateCounts = [];
        foreach ($records as $record) {
            $managerCode = $this->cleanText($record['ManagerPersonnelCode'] ?? null);
            $ownCode = $this->cleanText($record['PersonnelCode'] ?? null);

            // «مدیرِ خودش» (مدیرعامل) واحد نمی‌سازد.
            if ($managerCode !== null && $managerCode !== $ownCode) {
                $subordinateCounts[$managerCode] = ($subordinateCounts[$managerCode] ?? 0) + 1;
            }
        }

        foreach (array_keys($subordinateCounts) as $managerCode) {
            // PHP کلیدهای رشته‌ایِ عددی را خودکار به int تبدیل می‌کند؛ چون در ادامه با
            // خروجی رشته‌ایِ cleanText مقایسه می‌شود، باید صریحاً به رشته برگردد وگرنه
            // مقایسه‌ی === همیشه false می‌شود و هیچ پرسنلی به واحدش وصل نمی‌شد.
            $managerCode = (string) $managerCode;

            $managerRecord = $byCode[$managerCode] ?? null;
            $managerUser = $usersByCode->get($managerCode);

            if (!$managerRecord || !$managerUser) {
                continue;
            }

            $unit = $this->resolveUnitForManager($managerRecord, $managerUser, $deputies, $units);

            if (!$unit) {
                continue;
            }

            // اتصال زیردستان به واحد. deputy_id پاک می‌شود چون پرسنلِ دارای واحد نباید
            // هم‌زمان «مستقیمِ معاونت» هم باشد - نگاه کنید به User::effectiveDeputyId().
            $memberCodes = [];
            foreach ($records as $record) {
                if ($this->cleanText($record['ManagerPersonnelCode'] ?? null) === $managerCode
                    && $this->cleanText($record['PersonnelCode'] ?? null) !== $managerCode) {
                    $memberCodes[] = $this->cleanText($record['PersonnelCode'] ?? null);
                }
            }

            User::whereIn('personnel_code', array_filter($memberCodes))
                ->update(['unit_id' => $unit->id, 'deputy_id' => null]);
        }
    }

    /** واحدِ متناظر با یک مدیر را پیدا یا ایجاد می‌کند (یک واحد به‌ازای هر مدیر). */
    private function resolveUnitForManager(array $managerRecord, User $managerUser, array &$deputies, array &$units): ?Unit
    {
        $key = (string) $managerUser->id;

        if (isset($units[$key])) {
            return $units[$key];
        }

        $existing = Unit::where('manager_user_id', $managerUser->id)->first();

        if ($existing) {
            return $units[$key] = $existing;
        }

        $deputy = $this->resolveDeputy($managerRecord, $deputies);

        // عنوان واحد: نام مدیر، چون سیستم مبدأ عنوان واحد جداگانه‌ای ندارد. HR می‌تواند
        // بعداً از پنل عنوان دقیق‌تری بگذارد - همگام‌سازی بعدی آن را بازنویسی نمی‌کند
        // (تطبیق بر اساس manager_user_id است، نه عنوان).
        $title = trim(($managerUser->name ?? '') . ' ' . ($managerUser->family ?? ''));
        $title = $title !== '' ? __('واحد') . ' ' . $title : __('واحد') . ' ' . $managerUser->id;

        $created = Unit::create([
            'deputy_id' => $deputy?->id,
            'title' => $title,
            'manager_user_id' => $managerUser->id,
            'is_active' => true,
        ]);
        $this->stats['units_created']++;

        return $units[$key] = $created;
    }

    /** معاونت را بر اساس DepartmentType پیدا یا ایجاد می‌کند. */
    private function resolveDeputy(array $record, array &$cache): ?Deputy
    {
        $code = $this->cleanText($record['DepartmentType'] ?? null);
        $title = $this->cleanText($record['DepartmentName'] ?? null);

        if ($code === null || $title === null) {
            return null;
        }

        if (isset($cache[$code])) {
            return $cache[$code];
        }

        $existing = Deputy::where('title', $title)->first();

        if ($existing) {
            return $cache[$code] = $existing;
        }

        $created = Deputy::create(['title' => $title, 'is_active' => true]);
        $this->stats['deputies_created']++;

        return $cache[$code] = $created;
    }

    /** نوع قرارداد را بر اساس EmploymentType پیدا یا ایجاد می‌کند. */
    private function resolveContractType(array $record, array &$cache): ?ContractType
    {
        $code = $record['EmploymentType'] ?? null;
        $title = $this->cleanText($record['EmploymentName'] ?? null);

        if ($code === null || $code === '') {
            return null;
        }

        $code = (int) $code;

        if (isset($cache[$code])) {
            return $cache[$code];
        }

        $existing = ContractType::where('external_code', $code)->first();

        if ($existing) {
            // عنوان اگر در مبدأ اصلاح شده باشد به‌روز می‌شود، ولی سقف‌های تعریف‌شده
            // توسط HR (که به id این رکورد وصل‌اند) دست‌نخورده باقی می‌مانند.
            if ($title !== null && $existing->title !== $title) {
                $existing->update(['title' => $title]);
                $this->stats['contract_types_updated']++;
            }

            return $cache[$code] = $existing;
        }

        // اگر نوع قراردادی با همین عنوان از قبل دستی ساخته شده باشد، به‌جای ساخت رکورد
        // تکراری، همان را به کد مبدأ متصل می‌کنیم.
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
     * سمت سازمانی را بر اساس **عنوان** (PostName) پیدا یا ایجاد می‌کند.
     *
     * سمت واحد گروه‌بندی سقف اضافه‌کار است (بند ۴.۳: تعداد پرسنل × سقف هر نفر)، پس باید
     * عنوان شغلی باشد نه شناسه‌ی جایگاهِ تک‌نفره. ساختار جدید سرویس دقیقاً همین را
     * می‌دهد: پنج سمت (مدیرعامل/معاون/مدیر/رئیس گروه/کارشناس).
     */
    private function resolvePosition(array $record, array &$cache): ?Position
    {
        $title = $this->cleanText($record['PostName'] ?? null);

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

    /** پرسنل را بر اساس کد پرسنلی ایجاد یا به‌روزرسانی می‌کند. */
    private function upsertPersonnel(
        array $record,
        string $code,
        ?ContractType $contractType,
        ?Position $position,
        ?Deputy $deputy,
        ?User $user = null
    ): void {
        $firstName = $this->cleanText($record['FirstName'] ?? null) ?? '';
        $lastName = $this->cleanText($record['LastName'] ?? null);

        $attributes = [
            'name' => $firstName !== '' ? $firstName : $code,
            'family' => $lastName,
            'position_id' => $position?->id,
            'contract_type_id' => $contractType?->id,
            'personnel_code' => $code,
            'post_code' => $this->cleanText($record['PostType'] ?? null),
            'synced_at' => now(),
        ];

        // معاونت فعلاً مستقیم ست می‌شود؛ اگر بعداً واحد پیدا کند (مرحله‌ی چارت)، همان‌جا
        // unit_id ست و deputy_id پاک می‌شود.
        if ($user === null || $user->unit_id === null) {
            $attributes['deputy_id'] = $deputy?->id;
        }

        if ($user) {
            // عمداً username/email/password/cell دست نمی‌خورند: این‌ها اطلاعات ورود
            // کاربرند و سیستم مبدأ آن‌ها را ندارد.
            $user->update($attributes);
            $this->stats['personnel_updated']++;

            return;
        }

        // ستون‌های email و password در این جدول NOT NULL هستند ولی سرویس آن‌ها را نمی‌دهد؛
        // مقدار جایگزینِ یکتا می‌سازیم تا رکورد معتبر باشد. کاربر تا وقتی HR رمز واقعی
        // تنظیم نکند نمی‌تواند وارد شود (رمز تصادفی و دورانداختنی است).
        User::create($attributes + [
            'username' => $code,
            'email' => $code . '@' . config('services.personnel.email_domain', 'personnel.local'),
            'password' => Hash::make(Str::random(40)),
        ]);
        $this->stats['personnel_created']++;
    }

    /**
     * پاک‌سازی متن ورودی سرویس: یکسان‌سازی حروف عربی/ارقام و حذف فاصله‌ی اضافی.
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
