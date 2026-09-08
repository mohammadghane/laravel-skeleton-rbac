{{--
    فیلتر انتخاب ماه شمسی - تقریباً همه‌ی صفحات این ماژول به یک «دوره‌ی ماهانه» محدودند،
    پس این کنترل مشترک است.

    پارامترها:
      $year, $month  دوره‌ی جاری انتخاب‌شده
      $route         نام route ای که فرم به آن GET می‌زند
      $extra         آرایه‌ی [name => value] برای حفظ سایر پارامترهای query (اختیاری)
--}}
@php
    $months = [
        1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور',
        7 => 'مهر', 8 => 'آبان', 9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
    ];
    $currentYear = \App\Support\Jalali::currentYear();
    $control = 'rounded-lg border border-line bg-white px-3 py-2 text-sm focus:border-pine focus:outline-none focus:ring-2 focus:ring-pine/20';
@endphp

<form method="GET" action="{{ route($route) }}" class="flex flex-wrap items-center gap-2">
    @foreach (($extra ?? []) as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach

    <label class="text-sm text-ink/50">{{ __('دوره') }}</label>

    <select name="jalali_month" class="{{ $control }}">
        @foreach ($months as $number => $label)
            <option value="{{ $number }}" @selected($number === (int) $month)>{{ $label }}</option>
        @endforeach
    </select>

    <select name="jalali_year" class="{{ $control }} font-mono">
        @for ($y = $currentYear + 1; $y >= $currentYear - 3; $y--)
            <option value="{{ $y }}" @selected($y === (int) $year)>{{ $y }}</option>
        @endfor
    </select>

    <button type="submit" class="rounded-lg border border-line px-3 py-2 text-sm font-medium text-ink/70 transition hover:bg-surface">
        {{ __('نمایش') }}
    </button>
</form>
