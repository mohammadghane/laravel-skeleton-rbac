{{--
    نوار جست‌وجوی مشترک فهرست‌ها.

    ورودی کاربر پیش از رسیدن به کنترلر توسط میدل‌ور NormalizePersianInput یکسان می‌شود
    (ي/ك عربی => فارسی، ارقام فارسی/عربی => لاتین)، بنابراین تایپ با هر کیبوردی نتیجه‌ی
    یکسان می‌دهد.

    پارامترها:
      $route        نام route فهرست
      $placeholder  متن راهنمای فیلد (اختیاری)
      $extra        آرایه‌ی [name => value] برای حفظ سایر پارامترهای query (اختیاری)
--}}
@php
    $control = 'rounded-lg border border-line bg-white px-3 py-2 text-sm focus:border-pine focus:outline-none focus:ring-2 focus:ring-pine/20';
    $current = request('q');
@endphp

<form method="GET" action="{{ route($route) }}" class="flex flex-wrap items-center gap-2">
    {{-- مقادیر خالی ارسال نمی‌شوند تا آدرس صفحه با پارامترهای بی‌مقدار شلوغ نشود. --}}
    @foreach (($extra ?? []) as $name => $value)
        @if ($value !== null && $value !== '')
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endif
    @endforeach

    <div class="relative">
        <input
            type="search"
            name="q"
            value="{{ $current }}"
            placeholder="{{ $placeholder ?? __('جست‌وجو…') }}"
            class="{{ $control }} w-56 ps-8"
        >
        <span class="pointer-events-none absolute inset-y-0 start-2 flex items-center text-ink/30">⌕</span>
    </div>

    <button type="submit" class="rounded-lg border border-line px-3 py-2 text-sm font-medium text-ink/70 transition hover:bg-surface">
        {{ __('جست‌وجو') }}
    </button>

    @if ($current !== null && $current !== '')
        <a href="{{ route($route, $extra ?? []) }}" class="rounded-lg px-2 py-2 text-xs text-ink/50 underline hover:text-ink/70">
            {{ __('پاک کردن') }}
        </a>
    @endif
</form>
