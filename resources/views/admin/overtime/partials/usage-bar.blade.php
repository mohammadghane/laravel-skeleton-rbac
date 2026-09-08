{{--
    نوار درصد مصرف سقف.

    پارامتر: $percentage - عدد اعشاری درصد. می‌تواند از ۱۰۰ بیشتر باشد (عبور از سقف)؛
    در آن حالت خودِ نوار روی ۱۰۰٪ بریده می‌شود ولی عدد واقعی نمایش داده می‌شود.

    رنگ: زیر ۹۰٪ سبز (pine)، ۹۰ تا ۱۰۰ طلایی (هشدار نزدیک شدن)، بالای ۱۰۰ قرمز (عبور).
    آستانه‌ی ۹۰٪ همان آستانه‌ی پیش‌فرض OvertimeReportService::ceilingWarnings است.
--}}
@php
    $pct = (float) $percentage;
    $width = max(0, min(100, $pct));
    $tone = $pct >= 100 ? 'bg-brick' : ($pct >= 90 ? 'bg-gold' : 'bg-pine');
    $textTone = $pct >= 100 ? 'text-brick' : ($pct >= 90 ? 'text-ink/80' : 'text-ink/60');
@endphp

<div class="flex items-center gap-2">
    <div class="h-1.5 w-full min-w-16 overflow-hidden rounded-full bg-line" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100">
        <div class="h-full rounded-full {{ $tone }}" style="width: {{ $width }}%"></div>
    </div>
    <span class="shrink-0 font-mono text-xs {{ $textTone }}">{{ $pct }}٪</span>
</div>
