@extends('layouts.admin')

@section('title', __('ورود اطلاعات از اکسل'))

@section('content')
    @include('admin.overtime.partials.page-header', [
        'title' => __('ورود کسر کار و ماموریت از فایل اکسل'),
        'subtitle' => __('کسر کار و ماموریت فقط از طریق فایل اکسل وارد می‌شوند. این مقادیر در محاسبه‌ی سقف نقشی ندارند و صرفاً در گزارش نهایی و خروجی مالی نمایش داده می‌شوند.'),
    ])

    {{-- دو فرم کاملاً مجزا: هر کدام فایل، دوره و نتیجه‌ی مستقل خودش را دارد. --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        @include('admin.overtime.import.partials.form', [
            'title' => __('ورود کسر کار'),
            'hint' => __('فایل مربوط به ساعات کسر کار پرسنل.'),
            'action' => route('admin.overtime.import.deduction'),
            'year' => $year,
            'month' => $month,
        ])

        @include('admin.overtime.import.partials.form', [
            'title' => __('ورود ماموریت'),
            'hint' => __('فایل مربوط به تعداد روز ماموریت پرسنل.'),
            'action' => route('admin.overtime.import.mission'),
            'year' => $year,
            'month' => $month,
        ])
    </div>

    {{-- راهنمای ساختار فایل: هر دو فرم ساختار یکسانی دارند. --}}
    <div class="mt-6 rounded-2xl border border-line bg-white p-5 shadow-card">
        <h3 class="mb-3 text-base font-semibold text-ink">{{ __('ساختار فایل') }}</h3>
        <p class="mb-4 text-sm text-ink/60">
            {{ __('ساختار هر دو فایل یکسان است: سطر اول سربرگ ستون‌ها و شامل چهار ستون زیر.') }}
        </p>

        <div class="overflow-x-auto">
            <table class="w-full text-start text-sm">
                <thead>
                    <tr class="border-b border-line text-xs text-ink/50">
                        <th class="px-3 py-2 font-medium">{{ __('نام ستون') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('الزامی') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('توضیح') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line text-xs">
                    <tr>
                        <td class="px-3 py-2 font-medium text-ink">{{ __('کد پرسنلی') }}</td>
                        <td class="px-3 py-2 text-brick">{{ __('بله') }}</td>
                        <td class="px-3 py-2 text-ink/60">{{ __('مبنای تطبیق پرسنل. در صورت نبود، ستون «کد ملی» هم پذیرفته می‌شود.') }}</td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2 font-medium text-ink">{{ __('نام') }}</td>
                        <td class="px-3 py-2 text-ink/50">{{ __('خیر') }}</td>
                        <td class="px-3 py-2 text-ink/60">{{ __('فقط برای خوانایی فایل؛ در تطبیق پرسنل استفاده نمی‌شود.') }}</td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2 font-medium text-ink">{{ __('نام خانوادگی') }}</td>
                        <td class="px-3 py-2 text-ink/50">{{ __('خیر') }}</td>
                        <td class="px-3 py-2 text-ink/60">{{ __('فقط برای خوانایی فایل؛ در تطبیق پرسنل استفاده نمی‌شود.') }}</td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2 font-medium text-ink">{{ __('ساعت/روز') }}</td>
                        <td class="px-3 py-2 text-brick">{{ __('بله') }}</td>
                        <td class="px-3 py-2 text-ink/60">{{ __('مقدار عددی؛ بسته به فرم، ساعت کسر کار یا تعداد روز ماموریت.') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- نمونه‌ی بصری تا کاربر ساختار را بدون آزمون‌وخطا بفهمد. --}}
        <p class="mb-2 mt-5 text-sm font-medium text-ink/70">{{ __('نمونه:') }}</p>
        <div class="overflow-x-auto">
            <table class="w-full border border-line text-start text-xs">
                <thead class="bg-surface">
                    <tr class="text-ink/60">
                        <th class="border border-line px-3 py-2 font-medium">{{ __('کد پرسنلی') }}</th>
                        <th class="border border-line px-3 py-2 font-medium">{{ __('نام') }}</th>
                        <th class="border border-line px-3 py-2 font-medium">{{ __('نام خانوادگی') }}</th>
                        <th class="border border-line px-3 py-2 font-medium">{{ __('ساعت/روز') }}</th>
                    </tr>
                </thead>
                <tbody class="text-ink/70">
                    <tr>
                        <td class="border border-line px-3 py-2 font-mono">1024</td>
                        <td class="border border-line px-3 py-2">نگار</td>
                        <td class="border border-line px-3 py-2">احمدی</td>
                        <td class="border border-line px-3 py-2 font-mono">4.5</td>
                    </tr>
                    <tr>
                        <td class="border border-line px-3 py-2 font-mono">1037</td>
                        <td class="border border-line px-3 py-2">امیر</td>
                        <td class="border border-line px-3 py-2">حسینی</td>
                        <td class="border border-line px-3 py-2 font-mono">12</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="mt-3 text-xs text-ink/40">
            {{ __('ردیف‌هایی که پرسنل آن‌ها پیدا نشود یا ساعت نامعتبر داشته باشند گزارش می‌شوند و بقیه‌ی فایل ثبت می‌گردد؛ یک ردیف نامعتبر کل فایل را رد نمی‌کند.') }}
        </p>
    </div>
@endsection
