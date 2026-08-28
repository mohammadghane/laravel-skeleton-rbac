{{--
    یک فرم بارگذاری اکسل. چون کسر کار و ماموریت ساختار یکسانی دارند (چهار ستون: کد
    پرسنلی، نام، نام خانوادگی، ساعت)، همین partial با پارامترهای متفاوت دوبار استفاده
    می‌شود.

    پارامترها:
      $title   عنوان کارت
      $hint    توضیح یک‌خطی زیر عنوان
      $action  آدرس ارسال فرم
      $year, $month  دوره‌ی پیش‌فرض
--}}
@php
    $input = 'w-full rounded-lg border border-line px-3 py-2 text-sm focus:border-pine focus:outline-none focus:ring-2 focus:ring-pine/20';
    $label = 'mb-1.5 block text-sm font-medium text-ink/70';
    $months = [1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان', 9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند'];
    $currentYear = \App\Support\Jalali::currentYear();
@endphp

{{-- x-data روی هر فرم جداگانه است تا نتیجه‌ی هر بارگذاری زیر همان فرم نمایش داده شود
     و دو فرم روی نتیجه‌ی هم اثر نگذارند. --}}
<div
    class="rounded-2xl border border-line bg-white p-5 shadow-card"
    x-data="{
        busy: false,
        result: null,
        async submit(event) {
            this.busy = true;
            this.result = null;
            try {
                const res = await fetch(event.target.action, {
                    method: 'POST',
                    body: new FormData(event.target),
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                });
                const body = await res.json();
                if (!res.ok) {
                    const messages = body.errors ? Object.values(body.errors).flat() : [body.message || '{{ __('خطا در بارگذاری فایل') }}'];
                    this.result = { imported: 0, errors: messages };
                } else {
                    this.result = body;
                    event.target.reset();
                }
            } catch {
                this.result = { imported: 0, errors: ['{{ __('اتصال به سرور برقرار نشد.') }}'] };
            } finally {
                this.busy = false;
            }
        },
    }"
>
    <h3 class="mb-1 text-base font-semibold text-ink">{{ $title }}</h3>
    <p class="mb-4 text-xs text-ink/50">{{ $hint }}</p>

    <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-4" @submit.prevent="submit($event)">
        @csrf

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="{{ $label }}">{{ __('ماه') }}</label>
                <select name="jalali_month" class="{{ $input }}">
                    @foreach ($months as $number => $monthLabel)
                        <option value="{{ $number }}" @selected($number === $month)>{{ $monthLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="{{ $label }}">{{ __('سال') }}</label>
                <select name="jalali_year" class="{{ $input }} font-mono">
                    @for ($y = $currentYear + 1; $y >= $currentYear - 3; $y--)
                        <option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>
                    @endfor
                </select>
            </div>
        </div>

        <div>
            <label class="{{ $label }}">{{ __('فایل اکسل') }}</label>
            <input class="{{ $input }}" type="file" name="file" accept=".xlsx,.xls,.csv" required>
        </div>

        <button type="submit" :disabled="busy" class="w-full rounded-lg bg-pine px-4 py-2.5 text-sm font-medium text-white transition hover:bg-pine-600 disabled:opacity-60">
            <span x-show="!busy">{{ __('بارگذاری و ثبت') }}</span>
            <span x-show="busy">{{ __('در حال پردازش…') }}</span>
        </button>
    </form>

    {{-- نتیجه‌ی بارگذاری همین فرم --}}
    <template x-if="result">
        <div class="mt-4 border-t border-line pt-4">
            <p class="mb-2 rounded-lg border border-pine/30 bg-pine-100 px-3 py-2 text-sm text-pine" x-show="result.imported > 0">
                <span x-text="result.imported"></span> {{ __('ردیف با موفقیت ثبت شد.') }}
            </p>

            <template x-if="result.errors && result.errors.length">
                <div>
                    <p class="mb-2 text-sm font-medium text-brick">{{ __('ردیف‌های ثبت‌نشده:') }}</p>
                    <ul class="max-h-48 space-y-1 overflow-y-auto">
                        <template x-for="(err, i) in result.errors" :key="i">
                            <li class="rounded-lg border border-brick/20 bg-brick-100 px-3 py-2 text-xs text-brick" x-text="err"></li>
                        </template>
                    </ul>
                </div>
            </template>

            <p class="text-sm text-ink/50" x-show="result.imported === 0 && (!result.errors || !result.errors.length)">
                {{ __('هیچ ردیفی ثبت نشد.') }}
            </p>
        </div>
    </template>
</div>
