<?php

namespace App\Notifications;

use App\Support\Jalali;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * هشدار نزدیک‌شدن/عبور از سقف اضافه‌کار به مدیر منابع انسانی و مدیر سازمان ( سند:
 * «هشدار به مدیر سرمایه انسانی و مدیر سازمان در صورت نزدیک شدن یا عبور از سقف گروهی»).
 * $warnings ساختار خروجی OvertimeReportService::ceilingWarnings() است.
 */
class OvertimeCeilingWarningNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Collection $warnings,
        private readonly int $jalaliYear,
        private readonly int $jalaliMonth,
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $period = Jalali::periodLabel($this->jalaliYear, $this->jalaliMonth);
        $exceeded = $this->warnings->where('level', 'exceeded');
        $near = $this->warnings->where('level', 'near');

        $mail = (new MailMessage)
            ->subject("هشدار سقف اضافه‌کار - دوره‌ی {$period}")
            ->greeting('سلام،')
            ->line("در بررسی دوره‌ی {$period}، {$this->warnings->count()} مورد نزدیک به سقف یا فراتر از سقف مجاز اضافه‌کار شناسایی شد.");

        if ($exceeded->isNotEmpty()) {
            $mail->line('موارد عبور از سقف:');
            foreach ($exceeded as $w) {
                $mail->line("- {$w['scope_title']} / {$w['item_title']}: مصرف {$w['used']} از سقف {$w['ceiling']} ({$w['percentage']}٪)");
            }
        }

        if ($near->isNotEmpty()) {
            $mail->line('موارد نزدیک به سقف:');
            foreach ($near as $w) {
                $mail->line("- {$w['scope_title']} / {$w['item_title']}: مصرف {$w['used']} از سقف {$w['ceiling']} ({$w['percentage']}٪)");
            }
        }

        return $mail->line('لطفاً گزارش تفصیلی را در پنل اضافه‌کار بررسی کنید.');
    }

    public function toArray($notifiable): array
    {
        return [
            'jalali_year' => $this->jalaliYear,
            'jalali_month' => $this->jalaliMonth,
            'warnings_count' => $this->warnings->count(),
            'exceeded_count' => $this->warnings->where('level', 'exceeded')->count(),
            'warnings' => $this->warnings->values()->all(),
        ];
    }
}
