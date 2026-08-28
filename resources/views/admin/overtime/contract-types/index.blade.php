@extends('layouts.admin')

@section('title', __('انواع قرارداد'))

@section('content')
    @php
        $input = 'w-full rounded-lg border border-line px-3 py-2 text-sm focus:border-pine focus:outline-none focus:ring-2 focus:ring-pine/20';
        $label = 'mb-1.5 block text-sm font-medium text-ink/70';
        $months = [1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان', 9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند'];
        $currentYear = \App\Support\Jalali::currentYear();
    @endphp

    @include('admin.overtime.partials.page-header', [
        'title' => __('انواع قرارداد استخدامی'),
        'subtitle' => __('سقف اضافه‌کار و روز فعال‌سازی یک‌بار تعریف می‌شوند و تا زمانی که تغییرشان ندهید، برای ماه‌های بعد هم معتبرند.'),
        'action' => new \Illuminate\Support\HtmlString(
            '<a href="' . route('admin.overtime.contract-types.create') . '" class="open-modal inline-flex items-center gap-2 rounded-lg bg-pine px-4 py-2.5 text-sm font-medium text-white transition hover:bg-pine-600">+ ' . __('نوع قرارداد جدید') . '</a>'
        ),
    ])

    {{-- توضیح صریح رفتار «ارث‌بری» - مهم‌ترین سوءتفاهم احتمالی کاربر در این صفحه. --}}
    <div class="mb-4 rounded-xl border border-line bg-surface px-4 py-3 text-sm text-ink/60">
        {{ __('نیازی به ثبت ماهانه‌ی سقف نیست. هر مقداری که برای یک ماه ثبت کنید، از همان ماه به بعد اعمال می‌شود و تا وقتی نسخه‌ی جدیدی ثبت نکنید ادامه می‌یابد.') }}
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-3">
        @include('admin.overtime.partials.month-filter', [
            'year' => $year, 'month' => $month, 'route' => 'admin.overtime.contract-types.index',
            'extra' => ['q' => request('q')],
        ])

        @include('admin.overtime.partials.search-box', [
            'route' => 'admin.overtime.contract-types.index',
            'placeholder' => __('عنوان نوع قرارداد…'),
            'extra' => ['jalali_year' => $year, 'jalali_month' => $month],
        ])
    </div>

    {{-- x-data روی کل بخش: انتخاب ردیف‌ها و پنل ویرایش گروهی با هم کار می‌کنند. --}}
    <div x-data="bulkCeilings()">
        {{-- نوار اقدام گروهی: فقط وقتی چیزی انتخاب شده باشد دیده می‌شود. --}}
        <div x-show="selected.length > 0" class="mb-4 rounded-2xl border border-pine/30 bg-pine-100 p-4">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <span class="text-sm font-medium text-pine">
                    <span x-text="selected.length"></span> {{ __('نوع قرارداد انتخاب شده') }}
                </span>
                <button type="button" @click="clearSelection()" class="text-xs text-pine underline">{{ __('لغو انتخاب') }}</button>
            </div>

            <form class="js-ajax-form" method="POST" action="{{ route('admin.overtime.contract-types.ceilings.bulk') }}" data-callback="reloadContractTypeGrid">
                @csrf
                <template x-for="id in selected" :key="id">
                    <input type="hidden" name="contract_type_ids[]" :value="id">
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
                        <label class="{{ $label }}">{{ __('سقف کلی قرارداد') }}</label>
                        <input class="{{ $input }} font-mono" name="overall_ceiling" type="number" step="0.01" min="0" placeholder="{{ __('بدون تغییر') }}" x-bind:disabled="clearOverall">
                        <label class="mt-1 flex items-center gap-1.5 text-xs text-ink/50">
                            <input type="checkbox" name="clear_overall_ceiling" value="1" x-model="clearOverall" class="rounded border-line text-pine focus:ring-pine/20">
                            {{ __('حذف سقف کلی') }}
                        </label>
                    </div>

                    <div>
                        <label class="{{ $label }}">{{ __('روز فعال‌سازی') }}</label>
                        <input class="{{ $input }} font-mono" name="activation_day" type="number" min="1" max="31" placeholder="{{ __('بدون تغییر') }}">
                    </div>

                    <div class="flex items-end">
                        <button type="submit" class="w-full rounded-lg bg-pine px-4 py-2.5 text-sm font-medium text-white hover:bg-pine-600">{{ __('اعمال گروهی') }}</button>
                    </div>
                </div>

                <p class="mt-2 text-xs text-pine/80">
                    {{ __('فیلدهای خالی تغییر نمی‌کنند و مقدار فعلی خود را حفظ می‌کنند.') }}
                </p>
            </form>
        </div>

        <div id="contract-types-table" class="rounded-2xl border border-line bg-white shadow-card">
            @include('admin.overtime.contract-types.partials.table', ['contractTypes' => $contractTypes, 'year' => $year, 'month' => $month])
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function reloadContractTypeGrid() { window.location.reload(); }

    function bulkCeilings() {
        return {
            selected: [],
            clearOverall: false,
            toggle(id, checked) {
                this.selected = checked
                    ? [...this.selected, id]
                    : this.selected.filter(x => x !== id);
            },
            toggleAll(checked) {
                const boxes = document.querySelectorAll('.row-select');
                boxes.forEach(b => { b.checked = checked; });
                this.selected = checked ? [...boxes].map(b => Number(b.value)) : [];
            },
            clearSelection() {
                document.querySelectorAll('.row-select, .select-all').forEach(b => { b.checked = false; });
                this.selected = [];
            },
        };
    }
</script>
@endpush
