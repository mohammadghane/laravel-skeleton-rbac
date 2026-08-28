@extends('layouts.admin')

@section('title', __('سقف مازاد سمت') . ' - ' . $position->title)

@section('content')
    @php
        $input = 'w-full rounded-lg border border-line px-3 py-2 text-sm focus:border-pine focus:outline-none focus:ring-2 focus:ring-pine/20';
        $label = 'mb-1.5 block text-sm font-medium text-ink/70';
        $months = [1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان', 9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند'];
        $currentYear = \App\Support\Jalali::currentYear();
    @endphp

    @include('admin.overtime.partials.page-header', [
        'title' => __('سقف اضافه‌کار مازاد') . ' — ' . $position->title,
        'subtitle' => __('این سقف در اختیار معاونت است. از ماه ثبت به بعد اعمال می‌شود و تا ثبت نسخه‌ی جدید ادامه می‌یابد.'),
        'action' => new \Illuminate\Support\HtmlString(
            '<a href="' . route('admin.overtime.positions.index') . '" class="rounded-lg border border-line px-4 py-2.5 text-sm font-medium text-ink/70 transition hover:bg-surface">' . __('بازگشت به فهرست') . '</a>'
        ),
    ])

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-1">
            <div class="rounded-2xl border border-line bg-white p-5 shadow-card">
                <h3 class="mb-1 text-base font-semibold text-ink">{{ __('ثبت سقف مازاد جدید') }}</h3>
                <p class="mb-4 text-xs text-ink/50">{{ __('از ماه انتخابی به بعد اعمال می‌شود.') }}</p>

                <form class="js-ajax-form space-y-4" method="POST" action="{{ route('admin.overtime.positions.ceilings.store', $position) }}" data-callback="reloadCeilings">
                    @csrf

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="{{ $label }}">{{ __('ماه') }}</label>
                            <select name="jalali_month" class="{{ $input }}">
                                @foreach ($months as $number => $monthLabel)
                                    <option value="{{ $number }}" @selected($number === $defaultMonth)>{{ $monthLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="{{ $label }}">{{ __('سال') }}</label>
                            <select name="jalali_year" class="{{ $input }} font-mono">
                                @for ($y = $currentYear + 1; $y >= $currentYear - 3; $y--)
                                    <option value="{{ $y }}" @selected($y === $defaultYear)>{{ $y }}</option>
                                @endfor
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="{{ $label }}">{{ __('سقف عادی هر نفر — در اختیار مدیر واحد') }}</label>
                        <input class="{{ $input }} font-mono" name="manager_ceiling_per_person" type="number" step="0.01" min="0" placeholder="{{ __('مثال: ۹۰') }}">
                        <span class="mt-1 block text-xs text-ink/40">{{ __('سقف کل گروه = تعداد پرسنل دارای این سمت × این عدد (تبصره‌ی اول بند ۴.۲).') }}</span>
                    </div>

                    <div>
                        <label class="{{ $label }}">{{ __('سقف مازاد هر نفر — در اختیار معاونت') }}</label>
                        <input class="{{ $input }} font-mono" name="ceiling_per_person" type="number" step="0.01" min="0" required placeholder="{{ __('مثال: ۵') }}">
                        <span class="mt-1 block text-xs text-ink/40">{{ __('سقف مازاد کل معاونت = تعداد پرسنل دارای این سمت × این عدد.') }}</span>
                    </div>

                    <button type="submit" class="w-full rounded-lg bg-pine px-4 py-2.5 text-sm font-medium text-white hover:bg-pine-600">{{ __('ذخیره سقف') }}</button>
                </form>
            </div>

            <div class="mt-4 rounded-2xl border border-line bg-surface p-4 text-xs text-ink/60">
                <p class="mb-1 font-medium text-ink/70">{{ __('تعداد پرسنل دارای این سمت') }}</p>
                <p class="font-mono text-lg text-ink">{{ $headcount }} {{ __('نفر') }}</p>
            </div>
        </div>

        <div class="lg:col-span-2">
            <div class="rounded-2xl border border-line bg-white shadow-card">
                <div class="overflow-x-auto">
                    <table class="w-full text-start text-sm">
                        <thead>
                            <tr class="border-b border-line text-xs text-ink/50">
                                <th class="px-5 py-3 font-medium">{{ __('بازه‌ی اعتبار') }}</th>
                                <th class="px-5 py-3 font-medium">{{ __('سقف عادی') }}</th>
                                <th class="px-5 py-3 font-medium">{{ __('سقف مازاد') }}</th>
                                <th class="px-5 py-3 font-medium">{{ __('سقف مازاد کل') }}</th>
                                <th class="px-5 py-3 font-medium">{{ __('ثبت‌کننده') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @forelse ($versions as $index => $version)
                                @php $newer = $index > 0 ? $versions[$index - 1] : null; @endphp
                                <tr class="hover:bg-surface/60 {{ $index === 0 ? 'bg-pine-100/40' : '' }}">
                                    <td class="px-5 py-3.5">
                                        <span class="font-mono text-xs font-medium text-ink">{{ \App\Support\Jalali::periodLabel($version->jalali_year, $version->jalali_month) }}</span>
                                        @if ($newer)
                                            <span class="font-mono text-xs text-ink/40">{{ __('تا') }} {{ \App\Support\Jalali::periodLabel($newer->jalali_year, $newer->jalali_month) }}</span>
                                        @else
                                            <span class="ms-1 rounded-full border border-pine/30 bg-pine-100 px-2 py-0.5 text-[11px] font-medium text-pine">{{ __('جاری') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5 font-mono text-xs text-ink/70">
                                        {{ $version->manager_ceiling_per_person ?? '—' }}
                                    </td>
                                    <td class="px-5 py-3.5 font-mono text-xs text-ink/70">{{ $version->ceiling_per_person }}</td>
                                    <td class="px-5 py-3.5 font-mono text-xs text-ink/70">{{ bcmul((string) $headcount, (string) $version->ceiling_per_person, 2) }}</td>
                                    <td class="px-5 py-3.5 text-xs text-ink/50">{{ $version->creator ? trim($version->creator->name . ' ' . $version->creator->family) : '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-10 text-center text-ink/40">{{ __('هنوز سقفی برای این سمت تعریف نشده است.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function reloadCeilings() { window.location.reload(); }
</script>
@endpush
