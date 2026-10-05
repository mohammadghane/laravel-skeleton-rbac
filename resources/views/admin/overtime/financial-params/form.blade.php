    @php
        $input = 'w-full rounded-lg border border-line px-3 py-2 text-sm focus:border-pine focus:outline-none focus:ring-2 focus:ring-pine/20';
        $label = 'mb-1.5 block text-sm font-medium text-ink/70';
        $card = 'rounded-2xl border border-line bg-white p-5 shadow-card';
    @endphp

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- ستون چپ: فرم تنظیم پارامترها --}}
        <div>
            <div class="{{ $card }}">
                <h3 class="mb-4 text-base font-semibold text-ink">{{ __('تنظیم پارامترهای مالی') }}</h3>
                <p class="mb-4 text-sm text-ink/60">
                    {{ __('این پارامترها فقط در محاسبات مالی و خروجی اکسل استفاده می‌شوند و روی سقف اضافه‌کار تاثیر نمی‌گذارند.') }}
                </p>

                <form class="js-ajax-form space-y-4" method="POST" action="{{ route('admin.overtime.personnel.financial-params.update', $personnel) }}" data-callback="reloadPeriod">
                    @csrf
                    <input type="hidden" name="jalali_year" value="{{ $year }}">
                    <input type="hidden" name="jalali_month" value="{{ $month }}">

                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <label class="{{ $label }}">{{ __('ضریب ریالی') }}</label>
                            <input class="{{ $input }} font-mono" name="rial_coefficient" type="number" step="0.01" min="0" value="{{ old('rial_coefficient', $period?->rial_coefficient) }}" placeholder="{{ __('مثال: ۲۵۰۰۰۰') }}" required>
                            <span class="mt-1 block text-xs text-ink/40">{{ __('ضریب ریالی برای محاسبه مبلغ فیش حقوقی') }}</span>
                        </div>

                        <div>
                            <label class="{{ $label }}">{{ __('تعداد روزهای کارکرد') }}</label>
                            <input class="{{ $input }} font-mono" name="worked_days_count" type="number" step="1" min="0" max="31" value="{{ old('worked_days_count', $period?->worked_days_count) }}" placeholder="{{ __('مثال: ۳۰') }}" required>
                            <span class="mt-1 block text-xs text-ink/40">{{ __('تعداد روزهای کاری در این ماه') }}</span>
                        </div>
                    </div>

                    <button type="submit" class="rounded-lg bg-pine px-4 py-2.5 text-sm font-medium text-white hover:bg-pine-600">{{ __('ذخیره پارامترهای مالی') }}</button>
                </form>
            </div>

            {{-- پیش‌نمایش محاسبات --}}
            @if ($period)
                <div class="mt-6 {{ $card }}">
                    <h3 class="mb-4 text-base font-semibold text-ink">{{ __('پیش‌نمایش محاسبات') }}</h3>

                    <div class="grid grid-cols-1 gap-4">
                        <div class="rounded-xl border border-line bg-surface/50 p-4">
                            <dt class="text-sm text-ink/60">{{ __('ساعت قابل پرداخت') }}</dt>
                            <dd class="mt-1 text-2xl font-bold text-ink">{{ $period->payableHours() }} {{ __('ساعت') }}</dd>
                        </div>

                        <div class="rounded-xl border border-line bg-surface/50 p-4">
                            <dt class="text-sm text-ink/60">{{ __('مجموع خام') }}</dt>
                            <dd class="mt-1 text-2xl font-bold text-ink">{{ $period->totalHours() }} {{ __('ساعت') }}</dd>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- ستون راست: خلاصه پرسنل --}}
        <div class="space-y-4">
            <div class="{{ $card }}">
                <h3 class="mb-4 text-base font-semibold text-ink">{{ __('خلاصه پرسنل') }}</h3>

                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-ink/60">{{ __('کد پرسنلی') }}</dt>
                        <dd class="font-mono text-ink">{{ $personnel->username }}</dd>
                    </div>

                    <div class="flex justify-between">
                        <dt class="text-ink/60">{{ __('نام کامل') }}</dt>
                        <dd class="text-ink">{{ trim($personnel->name . ' ' . $personnel->family) }}</dd>
                    </div>

                    <div class="flex justify-between">
                        <dt class="text-ink/60">{{ __('نوع قرارداد') }}</dt>
                        <dd class="text-ink">{{ $personnel->contractType?->title ?? '—' }}</dd>
                    </div>

                    <div class="flex justify-between">
                        <dt class="text-ink/60">{{ __('سمت سازمانی') }}</dt>
                        <dd class="text-ink">{{ $personnel->position?->title ?? '—' }}</dd>
                    </div>

                    <div class="flex justify-between">
                        <dt class="text-ink/60">{{ __('واحد / معاونت') }}</dt>
                        <dd class="text-ink">{{ $personnel->unit?->title ?? $personnel->deputy?->title ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="{{ $card }}">
                <h3 class="mb-4 text-base font-semibold text-ink">{{ __('اضافه‌کار ثبت‌شده') }}</h3>

                <dl class="space-y-2.5 text-sm">
                    <div class="flex items-center justify-between">
                        <dt class="text-ink/60">{{ __('اضافه‌کار عادی') }}</dt>
                        <dd class="font-mono text-ink">{{ $period?->normal_hours ?? '۰.۰۰' }}</dd>
                    </div>

                    <div class="flex items-center justify-between">
                        <dt class="text-ink/60">{{ __('اضافه‌کار مازاد') }}</dt>
                        <dd class="font-mono text-ink">{{ $period?->excess_hours ?? '۰.۰۰' }}</dd>
                    </div>

                    <div class="flex items-center justify-between">
                        <dt class="text-ink/60">{{ __('آیتم‌های ویژه') }}</dt>
                        <dd class="font-mono text-ink">{{ number_format((float) ($period?->specialItemLines->sum('hours') ?? 0), 2) }}</dd>
                    </div>

                    <div class="flex items-center justify-between border-t border-line pt-2.5">
                        <dt class="font-medium text-ink/70">{{ __('جمع کل (خام)') }}</dt>
                        <dd class="font-mono font-medium text-ink">{{ $period?->totalHours() ?? '۰.۰۰' }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function reloadPeriod(result) {
            if (result.message) {
                window.dispatchEvent(new CustomEvent('toast', { detail: { message: result.message, type: 'success' } }));
            }
        }
    </script>
    @endpush
