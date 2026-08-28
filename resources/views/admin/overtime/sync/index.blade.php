@extends('layouts.admin')

@section('title', __('همگام‌سازی پرسنل'))

@section('content')
    @php
        $input = 'w-full rounded-lg border border-line px-3 py-2 text-sm focus:border-pine focus:outline-none focus:ring-2 focus:ring-pine/20';
        $label = 'mb-1.5 block text-sm font-medium text-ink/70';
        $card = 'rounded-2xl border border-line bg-white p-5 shadow-card';
    @endphp

    @include('admin.overtime.partials.page-header', [
        'title' => __('همگام‌سازی پرسنل از وب‌سرویس'),
        'subtitle' => __('دریافت فهرست پرسنل، انواع قرارداد و سمت‌های سازمانی از سیستم پرسنلی سازمان.'),
    ])

    <div
        class="grid grid-cols-1 gap-6 lg:grid-cols-3"
        x-data="{
            busy: false,
            result: null,
            async submit(event) {
                if (!confirm('{{ __('همگام‌سازی آغاز شود؟ این عملیات ممکن است چند لحظه طول بکشد.') }}')) return;
                this.busy = true;
                this.result = null;
                try {
                    const res = await fetch(event.target.action, {
                        method: 'POST',
                        body: new FormData(event.target),
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    });
                    const body = await res.json();
                    this.result = res.ok
                        ? body
                        : { failed: true, message: body.message || '{{ __('همگام‌سازی ناموفق بود.') }}' };
                } catch {
                    this.result = { failed: true, message: '{{ __('اتصال به سرور برقرار نشد.') }}' };
                } finally {
                    this.busy = false;
                }
            },
        }"
    >
        {{-- فرم اجرا --}}
        <div class="lg:col-span-1">
            <div class="{{ $card }}">
                <h3 class="mb-4 text-base font-semibold text-ink">{{ __('اجرای همگام‌سازی') }}</h3>

                <form method="POST" action="{{ route('admin.overtime.sync.store') }}" class="space-y-4" @submit.prevent="submit($event)">
                    @csrf

                    <div>
                        <label class="{{ $label }}">{{ __('آدرس وب‌سرویس') }}</label>
                        <input class="{{ $input }} font-mono text-xs" name="url" value="{{ $url }}" dir="ltr">
                        <span class="mt-1 block text-xs text-ink/40">
                            {{ __('خالی بگذارید تا از مقدار تنظیم‌شده در فایل پیکربندی استفاده شود.') }}
                        </span>
                    </div>

                    <button type="submit" :disabled="busy" class="w-full rounded-lg bg-pine px-4 py-2.5 text-sm font-medium text-white transition hover:bg-pine-600 disabled:opacity-60">
                        <span x-show="!busy">{{ __('شروع همگام‌سازی') }}</span>
                        <span x-show="busy">{{ __('در حال همگام‌سازی…') }}</span>
                    </button>
                </form>
            </div>

            {{-- وضعیت فعلی --}}
            <div class="{{ $card }} mt-4">
                <h3 class="mb-3 text-sm font-semibold text-ink">{{ __('وضعیت فعلی') }}</h3>
                <dl class="space-y-2 text-xs">
                    <div class="flex justify-between">
                        <dt class="text-ink/50">{{ __('پرسنل همگام‌شده') }}</dt>
                        <dd class="font-mono text-ink/70">{{ $counts['personnel'] }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-ink/50">{{ __('انواع قرارداد') }}</dt>
                        <dd class="font-mono text-ink/70">{{ $counts['contract_types'] }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-ink/50">{{ __('سمت‌های سازمانی') }}</dt>
                        <dd class="font-mono text-ink/70">{{ $counts['positions'] }}</dd>
                    </div>
                    <div class="flex justify-between border-t border-line pt-2">
                        <dt class="text-ink/50">{{ __('آخرین همگام‌سازی') }}</dt>
                        <dd class="font-mono text-ink/70">
                            {{ $lastSyncedAt ? \Carbon\Carbon::parse($lastSyncedAt)->format('Y/m/d H:i') : '—' }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <div class="space-y-6 lg:col-span-2">
            {{-- نتیجه‌ی اجرا --}}
            <template x-if="result">
                <div class="{{ $card }}">
                    <h3 class="mb-3 text-base font-semibold text-ink">{{ __('نتیجه‌ی همگام‌سازی') }}</h3>

                    <template x-if="result.failed">
                        <p class="rounded-lg border border-brick/30 bg-brick-100 px-3 py-2 text-sm text-brick" x-text="result.message"></p>
                    </template>

                    <template x-if="!result.failed">
                        <div>
                            <p class="mb-3 rounded-lg border border-pine/30 bg-pine-100 px-3 py-2 text-sm text-pine" x-text="result.message"></p>

                            <div class="overflow-x-auto">
                                <table class="w-full text-start text-sm">
                                    <thead>
                                        <tr class="border-b border-line text-xs text-ink/50">
                                            <th class="px-3 py-2 font-medium">{{ __('مورد') }}</th>
                                            <th class="px-3 py-2 font-medium">{{ __('ایجاد') }}</th>
                                            <th class="px-3 py-2 font-medium">{{ __('به‌روزرسانی') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-line text-xs">
                                        <tr>
                                            <td class="px-3 py-2 text-ink">{{ __('انواع قرارداد') }}</td>
                                            <td class="px-3 py-2 font-mono text-pine" x-text="result.stats.contract_types_created"></td>
                                            <td class="px-3 py-2 font-mono text-ink/60" x-text="result.stats.contract_types_updated"></td>
                                        </tr>
                                        <tr>
                                            <td class="px-3 py-2 text-ink">{{ __('سمت‌های سازمانی') }}</td>
                                            <td class="px-3 py-2 font-mono text-pine" x-text="result.stats.positions_created"></td>
                                            <td class="px-3 py-2 font-mono text-ink/60" x-text="result.stats.positions_updated"></td>
                                        </tr>
                                        <tr>
                                            <td class="px-3 py-2 text-ink">{{ __('پرسنل') }}</td>
                                            <td class="px-3 py-2 font-mono text-pine" x-text="result.stats.personnel_created"></td>
                                            <td class="px-3 py-2 font-mono text-ink/60" x-text="result.stats.personnel_updated"></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <template x-if="result.errors && result.errors.length">
                                <div class="mt-4">
                                    <p class="mb-2 text-sm font-medium text-brick">
                                        {{ __('رکوردهای رد شده') }} (<span x-text="result.errors.length"></span>):
                                    </p>
                                    <ul class="max-h-48 space-y-1 overflow-y-auto">
                                        <template x-for="(err, i) in result.errors" :key="i">
                                            <li class="rounded-lg border border-brick/20 bg-brick-100 px-3 py-2 text-xs text-brick" x-text="err"></li>
                                        </template>
                                    </ul>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </template>

            {{-- توضیح رفتار همگام‌سازی: بدون این، کاربر نمی‌داند چه چیزی بازنویسی می‌شود. --}}
            <div class="{{ $card }}">
                <h3 class="mb-3 text-base font-semibold text-ink">{{ __('این عملیات چه می‌کند؟') }}</h3>

                <ul class="space-y-2 text-sm text-ink/60">
                    <li class="flex gap-2">
                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-pine"></span>
                        <span>{{ __('انواع قرارداد بر اساس «کد نوع قرارداد» و سمت‌ها بر اساس «شناسه‌ی پست» ساخته یا به‌روز می‌شوند — نه بر اساس عنوان. بنابراین اصلاح نگارشی عنوان در سیستم مبدأ، رکورد تکراری نمی‌سازد.') }}</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-pine"></span>
                        <span>{{ __('نام، نام خانوادگی، سمت و نوع قرارداد هر پرسنل از سیستم مبدأ به‌روزرسانی می‌شود.') }}</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-gold"></span>
                        <span>{{ __('تخصیص واحد و معاونت، اطلاعات ورود (نام کاربری، ایمیل، رمز عبور) و سقف‌های تعریف‌شده دست‌نخورده می‌مانند؛ این‌ها در سیستم مبدأ وجود ندارند و تصمیم منابع انسانی هستند.') }}</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-gold"></span>
                        <span>{{ __('پرسنلی که از خروجی سرویس حذف شده باشد، در این سیستم حذف یا غیرفعال نمی‌شود (چون سوابق اضافه‌کار به او وابسته است).') }}</span>
                    </li>
                </ul>

                <p class="mt-4 rounded-lg border border-line bg-surface px-3 py-2 text-xs text-ink/50">
                    {{ __('این عملیات از طریق خط فرمان هم قابل اجراست:') }}
                    <code class="font-mono" dir="ltr">php artisan overtime:sync-personnel</code>
                </p>
            </div>
        </div>
    </div>
@endsection
