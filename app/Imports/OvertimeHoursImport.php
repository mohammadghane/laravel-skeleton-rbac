<?php

namespace App\Imports;

use App\Models\User;
use App\Services\OvertimePeriodService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * ایمپورت یکی از پارامترهای «مستقل» بند ۴.۲ که سند صراحتاً گفته فقط از طریق فایل اکسل
 * وارد می‌شوند: **کسر کار** یا **ماموریت**.
 *
 * هر بار فقط یک پارامتر ایمپورت می‌شود (دو فرم مجزا در UI)، چون فایل‌های کسر کار و
 * ماموریت در عمل از دو مسیر جداگانه تولید می‌شوند و ادغام آن‌ها در یک فایل، خطای
 * انسانی ایجاد می‌کرد.
 *
 * ساختار فایل (سطر اول = هدر، چهار ستون):
 *   کد پرسنلی | نام | نام خانوادگی | ساعت
 *
 * ستون‌های «نام» و «نام خانوادگی» فقط برای خوانایی فایل و بازبینی انسانی‌اند؛ تطبیق
 * پرسنل بر اساس «کد پرسنلی» (username) و در صورت نبود، کد ملی انجام می‌شود.
 *
 * این پارامترها در محاسبه‌ی سقف هیچ نقشی ندارند - فقط در گزارش نهایی و خروجی اکسل مالی
 * نمایش داده می‌شوند؛ بنابراین مثل آیتم‌های ویژه‌ی HR، روی دوره‌ی نهایی‌شده هم قابل ثبت‌اند
 * و گردش کار تایید را تغییر نمی‌دهند.
 *
 * ایمپورت **جزئی** است: ردیف نامعتبر در $errors گزارش می‌شود و بقیه‌ی فایل ادامه می‌یابد
 * تا یک ردیف غلط کل فایل HR را بلوکه نکند.
 */
class OvertimeHoursImport implements ToCollection, WithHeadingRow
{
    /** ستون‌های مجاز - کلید همان نام ستون در جدول overtime_periods است. */
    public const TYPE_DEDUCTION = 'deduction_hours';
    public const TYPE_MISSION = 'mission_hours';

    /** @var list<string> پیام خطای ردیف‌هایی که ایمپورت نشدند. */
    public array $errors = [];

    public int $importedCount = 0;

    public function __construct(
        private readonly string $type, // یکی از ثابت‌های TYPE_* بالا
        private readonly int $jalaliYear,
        private readonly int $jalaliMonth,
        private readonly OvertimePeriodService $periodService,
    ) {
    }

    /** عنوان فارسی پارامتر - در پیام‌های خطا و راهنمای ستون‌ها استفاده می‌شود. */
    public function label(): string
    {
        return $this->type === self::TYPE_DEDUCTION ? __('کسر کار') : __('ماموریت');
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +1 برای صفر-مبنا بودن، +1 برای سطر هدر

            $identifier = $this->firstFilled($row, ['کد پرسنلی', 'personnel_code', 'username'])
                ?? $this->firstFilled($row, ['کد ملی', 'national_code']);

            if ($identifier === null) {
                $this->errors[] = __('ردیف :row: کد پرسنلی خالی است.', ['row' => $rowNumber]);
                continue;
            }

            $personnel = User::where('username', $identifier)->orWhere('national_code', $identifier)->first();

            if (!$personnel) {
                $this->errors[] = __('ردیف :row: پرسنلی با کد «:id» یافت نشد.', ['row' => $rowNumber, 'id' => $identifier]);
                continue;
            }

            $hours = $this->firstFilled($row, ['ساعت', 'hours', $this->label()]);

            if ($hours === null) {
                $this->errors[] = __('ردیف :row: ستون «ساعت» خالی است.', ['row' => $rowNumber]);
                continue;
            }

            if (!is_numeric($hours) || (float) $hours < 0) {
                $this->errors[] = __('ردیف :row: مقدار ساعت («:value») معتبر نیست.', ['row' => $rowNumber, 'value' => $hours]);
                continue;
            }

            $period = $this->periodService->findOrCreateDraft($personnel, $this->jalaliYear, $this->jalaliMonth);
            $period->update([$this->type => $hours]);

            $this->importedCount++;
        }
    }

    /**
     * اولین کلید موجود و غیرخالی از میان نام‌های ممکن ستون را برمی‌گرداند.
     *
     * نکته‌ی مهم: پکیج Laravel Excel با WithHeadingRow عنوان ستون‌ها را از طریق Str::slug
     * به اسلاگ اسکی تبدیل می‌کند - مثلاً «کسر کار» تبدیل به `ksr_kar` می‌شود، نه `کسر_کار`.
     * بنابراین نام‌های ورودی را هم با همان تبدیل نرمال می‌کنیم تا مستقل از این‌که پکیج
     * دقیقاً چه اسلاگی تولید می‌کند، تطبیق درست انجام شود.
     */
    private function firstFilled(Collection $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            foreach ([$key, Str::slug($key, '_')] as $candidate) {
                $value = $row->get($candidate);

                if ($value !== null && $value !== '') {
                    return (string) $value;
                }
            }
        }

        return null;
    }
}
