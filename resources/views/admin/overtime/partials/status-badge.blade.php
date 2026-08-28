{{--
    نشان وضعیت گردش کار اضافه‌کار (App\Enums\OvertimePeriodStatus).
    پارامتر: $status - یک نمونه‌ی enum یا مقدار رشته‌ای آن.

    رنگ‌ها از همان توکن‌های پنل می‌آیند: pine (موفق/تاییدشده)، brick (رد شده)،
    gold (در انتظار اقدام)، خاکستری (پیش‌نویس).
--}}
@php
    $value = $status instanceof \App\Enums\OvertimePeriodStatus ? $status->value : (string) $status;
    $enum = \App\Enums\OvertimePeriodStatus::tryFrom($value);

    $tone = match ($value) {
        'finalized', 'ceo_approved' => 'border-pine/30 bg-pine-100 text-pine',
        'deputy_rejected', 'ceo_rejected' => 'border-brick/30 bg-brick-100 text-brick',
        'manager_submitted', 'deputy_approved' => 'border-gold/40 bg-gold-100 text-ink/80',
        default => 'border-line bg-surface text-ink/50',
    };
@endphp

<span class="inline-flex whitespace-nowrap rounded-full border px-2.5 py-1 text-xs font-medium {{ $tone }}">
    {{ $enum?->label() ?? $value }}
</span>
