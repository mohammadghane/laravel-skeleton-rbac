<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\OvertimeCeilingWarningNotification;
use App\Services\OvertimeReportService;
use App\Support\Jalali;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * php artisan overtime:send-ceiling-warnings {--threshold=90} {--year=} {--month=}
 *
 * برای هشدار خودکار به مدیر منابع انسانی و مدیر سازمان (بند ۴.۷ سند)، طبق زمان‌بندی
 * routes/console.php اجرا می‌شود. اگر هیچ موردی به سقف نزدیک/فراتر نباشد، هیچ ایمیلی
 * ارسال نمی‌شود (بدون اسپم کردن مدیران در روزهای عادی).
 */
class SendOvertimeCeilingWarnings extends Command
{
    protected $signature = 'overtime:send-ceiling-warnings {--threshold=90} {--year=} {--month=}';

    protected $description = 'ارسال هشدار نزدیک‌شدن/عبور از سقف اضافه‌کار به منابع انسانی و مدیر سازمان';

    public function handle(OvertimeReportService $reports): int
    {
        $threshold = (int) $this->option('threshold');
        $year = (int) ($this->option('year') ?: Jalali::currentYear());
        $month = (int) ($this->option('month') ?: Jalali::currentMonth());

        $warnings = $reports->ceilingWarnings($year, $month, $threshold);

        if ($warnings->isEmpty()) {
            $this->info("هیچ موردی نزدیک/فراتر از سقف (آستانه {$threshold}٪) برای " . Jalali::periodLabel($year, $month) . ' یافت نشد.');

            return self::SUCCESS;
        }

        $recipients = $this->resolveRecipients();

        if ($recipients->isEmpty()) {
            $this->warn('هیچ کاربری با دسترسی overtime/hr-manage یا overtime/ceo-approve یافت نشد؛ هشداری ارسال نشد.');

            return self::SUCCESS;
        }

        Notification::send($recipients, new OvertimeCeilingWarningNotification($warnings, $year, $month));

        $this->info("{$warnings->count()} هشدار به {$recipients->count()} گیرنده ارسال شد.");

        return self::SUCCESS;
    }

    /**
     * کاربران واجد شرایط دریافت هشدار: هرکس نقشی داشته باشد که یکی از دو Permission
     * overtime/hr-manage یا overtime/ceo-approve به آن اختصاص داده شده، به‌علاوه‌ی
     * system-manager (طبق Gate::before در AclServiceProvider، این‌ها بایپس همه‌ی
     * دسترسی‌ها را دارند). این یک کوئری مستقیم روی roles/permissions است (نه پیمایش
     * تک‌تک کاربرها) - چون ساختار جدول در این پروژه مشخص است.
     */
    private function resolveRecipients()
    {
        return User::query()
            ->whereHas('roles.permissions', function ($q) {
                $q->whereIn('key', ['overtime/hr-manage', 'overtime/ceo-approve', 'system-manager']);
            })
            ->get()
            ->unique('id');
    }
}
