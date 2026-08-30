@extends('layouts.admin')

@section('title', __('سمت‌های سازمانی'))

@section('content')
    @php
        $input = 'w-full rounded-lg border border-line px-3 py-2 text-sm focus:border-pine focus:outline-none focus:ring-2 focus:ring-pine/20';
        $label = 'mb-1.5 block text-sm font-medium text-ink/70';
        $months = [1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان', 9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند'];
        $currentYear = \App\Support\Jalali::currentYear();
    @endphp

    @include('admin.overtime.partials.page-header', [
        'title' => __('سمت‌های سازمانی'),
        'subtitle' => __('سقف اضافه‌کار مازاد (در اختیار معاونت) بر اساس سمت سازمانی محاسبه می‌شود — بند ۴.۳ قانون دوم.'),
        'action' => new \Illuminate\Support\HtmlString(
            '<a href="' . route('admin.overtime.positions.create') . '" class="open-modal inline-flex items-center gap-2 rounded-lg bg-pine px-4 py-2.5 text-sm font-medium text-white transition hover:bg-pine-600">+ ' . __('سمت جدید') . '</a>'
        ),
    ])

    <div class="mb-4 rounded-xl border border-line bg-surface px-4 py-3 text-sm text-ink/60">
        {{ __('نیازی به ثبت ماهانه نیست. سقفی که برای یک ماه ثبت می‌کنید از همان ماه به بعد اعمال می‌شود و تا ثبت نسخه‌ی جدید ادامه می‌یابد.') }}
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-3">
        @include('admin.overtime.partials.month-filter', [
            'year' => $year, 'month' => $month, 'route' => 'admin.overtime.positions.index',
            'extra' => ['q' => request('q')],
        ])

        @include('admin.overtime.partials.search-box', [
            'route' => 'admin.overtime.positions.index',
            'placeholder' => __('عنوان سمت…'),
            'extra' => ['jalali_year' => $year, 'jalali_month' => $month],
        ])
    </div>

    <div x-data="bulkPositionCeilings(
        '{{ route('admin.overtime.positions.bulk-destroy') }}',
        '{{ __('حذف :count سمت انتخاب‌شده؟ سمت‌هایی که پرسنل دارند حذف نمی‌شوند.') }}'
    )">
        @include('admin.overtime.partials.bulk-delete', ['label' => __('سمت')])

        <div x-show="selected.length > 0" class="mb-4 rounded-2xl border border-pine/30 bg-pine-100 p-4">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <span class="text-sm font-medium text-pine">
                    <span x-text="selected.length"></span> {{ __('سمت انتخاب شده') }}
                </span>
                <button type="button" @click="clearSelection()" class="text-xs text-pine underline">{{ __('لغو انتخاب') }}</button>
            </div>

            <form class="js-ajax-form" method="POST" action="{{ route('admin.overtime.positions.ceilings.bulk') }}" data-callback="reloadPositionGrid">
                @csrf
                <template x-for="id in selected" :key="id">
                    <input type="hidden" name="position_ids[]" :value="id">
                </template>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="{{ $label }}">{{ __('اعمال از ماه') }}</label>
                        <div class="flex gap-2">
                            <select name="jalali_month" class="{{ $input }}">
                                @foreach ($months as $number => $monthLabel)
                                    <option value="{{ $number }}" @selected($number === $month)>{{ $monthLabel }}</option>
                                @endforeach
                            </select>
                            <select name="jalali_year" class="{{ $input }} font-mono">
                                @for ($y = $currentYear + 1; $y >= $currentYear - 3; $y--)
                                    <option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>
                                @endfor
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="{{ $label }}">{{ __('سقف عادی هر نفر') }}</label>
                        <input class="{{ $input }} font-mono" name="manager_ceiling_per_person" type="number" step="0.01" min="0" placeholder="{{ __('بدون تغییر') }}">
                        <span class="mt-1 block text-xs text-pine/70">{{ __('در اختیار مدیر واحد — مثال: ۹۰') }}</span>
                    </div>

                    <div>
                        <label class="{{ $label }}">{{ __('سقف مازاد هر نفر') }}</label>
                        <input class="{{ $input }} font-mono" name="ceiling_per_person" type="number" step="0.01" min="0" placeholder="{{ __('بدون تغییر') }}">
                        <span class="mt-1 block text-xs text-pine/70">{{ __('در اختیار معاونت — مثال: ۵') }}</span>
                    </div>

                    <div class="flex items-start">
                        <button type="submit" class="mt-7 w-full rounded-lg bg-pine px-4 py-2.5 text-sm font-medium text-white hover:bg-pine-600">{{ __('اعمال گروهی') }}</button>
                    </div>
                </div>

                <p class="mt-2 text-xs text-pine/80">{{ __('فیلدهای خالی تغییر نمی‌کنند و مقدار فعلی خود را حفظ می‌کنند.') }}</p>
            </form>
        </div>

        <div id="positions-table" class="rounded-2xl border border-line bg-white shadow-card">
            @include('admin.overtime.positions.partials.table', ['positions' => $positions, 'year' => $year, 'month' => $month])
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function reloadPositionGrid() { window.location.reload(); }

    // منطق انتخاب ردیف‌ها و حذف گروهی از bulkDelete مشترک (در layouts/admin.blade.php)
    // می‌آید؛ همان انتخاب هم برای «اعمال گروهی سقف» استفاده می‌شود.
    function bulkPositionCeilings(deleteUrl, confirmMessage) {
        return bulkDelete(deleteUrl, confirmMessage);
    }
</script>
@endpush
