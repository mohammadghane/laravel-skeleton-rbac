@extends('layouts.admin')

@section('title', __('داشبورد اضافه‌کار'))

@section('content')
    @php
        $card = 'rounded-2xl border border-line bg-white shadow-card';
        // بیشینه برای مقیاس نمودارها؛ صفر نشود تا تقسیم بر صفر رخ ندهد.
        $maxComparison = max(1, (float) $comparison->max('total_hours'));
        $maxHistory = max(1, (float) $history->max('total_hours'));
    @endphp

    @include('admin.overtime.partials.page-header', [
        'title' => __('داشبورد اضافه‌کار'),
        'subtitle' => __('مصرف سقف، هشدارها و مقایسه‌ی معاونت‌ها برای دوره‌ی') . ' ' . \App\Support\Jalali::periodLabel($year, $month),
    ])

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        @include('admin.overtime.partials.month-filter', [
            'year' => $year, 'month' => $month, 'route' => 'admin.overtime.dashboard',
        ])

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.overtime.reports.personnel-monthly', ['jalali_year' => $year, 'jalali_month' => $month]) }}" class="rounded-lg border border-line px-4 py-2.5 text-sm font-medium text-ink/70 transition hover:bg-surface">
                {{ __('گزارش تفصیلی پرسنل') }}
            </a>
            @permission('overtime/hr-manage')
                <a href="{{ route('admin.overtime.reports.export', ['jalali_year' => $year, 'jalali_month' => $month]) }}" class="rounded-lg bg-pine px-4 py-2.5 text-sm font-medium text-white transition hover:bg-pine-600">
                    {{ __('خروجی اکسل مالی') }}
                </a>
            @endpermission
        </div>
    </div>

    {{-- هشدارهای سقف --}}
    @if ($warnings->isNotEmpty())
        <div class="mb-6 {{ $card }} p-5">
            <h3 class="mb-3 text-base font-semibold text-ink">{{ __('هشدارهای سقف') }}</h3>
            <div class="space-y-2">
                @foreach ($warnings as $warning)
                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border px-3 py-2 text-sm {{ $warning['level'] === 'exceeded' ? 'border-brick/30 bg-brick-100' : 'border-gold/40 bg-gold-100' }}">
                        <span class="{{ $warning['level'] === 'exceeded' ? 'text-brick' : 'text-ink/80' }}">
                            <span class="font-medium">{{ $warning['scope_title'] }}</span>
                            <span class="text-xs opacity-70">/ {{ $warning['item_title'] }}</span>
                            —
                            {{ $warning['level'] === 'exceeded' ? __('عبور از سقف') : __('نزدیک به سقف') }}
                        </span>
                        <span class="font-mono text-xs {{ $warning['level'] === 'exceeded' ? 'text-brick' : 'text-ink/70' }}">
                            {{ $warning['used'] }} / {{ $warning['ceiling'] }} ({{ $warning['percentage'] }}٪)
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- مصرف سقف عادی به تفکیک نوع قرارداد --}}
        <div class="{{ $card }}">
            <h3 class="border-b border-line px-5 py-4 text-base font-semibold text-ink">{{ __('مصرف سقف عادی — به تفکیک نوع قرارداد') }}</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-start text-sm">
                    <thead>
                        <tr class="border-b border-line text-xs text-ink/50">
                            <th class="px-5 py-3 font-medium">{{ __('نوع قرارداد') }}</th>
                            <th class="px-5 py-3 font-medium">{{ __('نفر') }}</th>
                            <th class="px-5 py-3 font-medium">{{ __('مصرف / سقف') }}</th>
                            <th class="px-5 py-3 font-medium w-40">{{ __('درصد') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($normal as $row)
                            <tr class="hover:bg-surface/60">
                                <td class="px-5 py-3.5 font-medium text-ink">{{ $row['title'] }}</td>
                                <td class="px-5 py-3.5 font-mono text-xs text-ink/60">{{ $row['headcount'] }}</td>
                                <td class="px-5 py-3.5 font-mono text-xs text-ink/70">{{ $row['used'] }} / {{ $row['ceiling'] }}</td>
                                <td class="px-5 py-3.5">@include('admin.overtime.partials.usage-bar', ['percentage' => $row['percentage']])</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-8 text-center text-ink/40">{{ __('داده‌ای برای این دوره وجود ندارد.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- مصرف سقف مازاد به تفکیک سمت --}}
        <div class="{{ $card }}">
            <h3 class="border-b border-line px-5 py-4 text-base font-semibold text-ink">{{ __('مصرف سقف مازاد — به تفکیک سمت') }}</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-start text-sm">
                    <thead>
                        <tr class="border-b border-line text-xs text-ink/50">
                            <th class="px-5 py-3 font-medium">{{ __('سمت سازمانی') }}</th>
                            <th class="px-5 py-3 font-medium">{{ __('نفر') }}</th>
                            <th class="px-5 py-3 font-medium">{{ __('مصرف / سقف') }}</th>
                            <th class="px-5 py-3 font-medium w-40">{{ __('درصد') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($excess as $row)
                            <tr class="hover:bg-surface/60">
                                <td class="px-5 py-3.5 font-medium text-ink">{{ $row['title'] }}</td>
                                <td class="px-5 py-3.5 font-mono text-xs text-ink/60">{{ $row['headcount'] }}</td>
                                <td class="px-5 py-3.5 font-mono text-xs text-ink/70">{{ $row['used'] }} / {{ $row['ceiling'] }}</td>
                                <td class="px-5 py-3.5">@include('admin.overtime.partials.usage-bar', ['percentage' => $row['percentage']])</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-8 text-center text-ink/40">{{ __('در این سطح، سقف مازاد معنا ندارد یا داده‌ای ثبت نشده.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- نمودار مقایسه‌ای معاونت‌ها + میانگین هر معاونت --}}
    @if ($isPrivileged && $comparison->isNotEmpty())
        <div class="mt-6 {{ $card }} p-5">
            <h3 class="mb-4 text-base font-semibold text-ink">{{ __('مقایسه‌ی معاونت‌ها') }}</h3>

            <div class="space-y-3">
                @foreach ($comparison as $row)
                    <div>
                        <div class="mb-1 flex flex-wrap items-center justify-between gap-2 text-sm">
                            <span class="font-medium text-ink">{{ $row['deputy_title'] }}</span>
                            <span class="font-mono text-xs text-ink/50">
                                {{ __('جمع') }}: {{ $row['total_hours'] }} —
                                {{ __('میانگین هر نفر') }}: {{ $row['average_per_person'] }}
                                ({{ $row['headcount'] }} {{ __('نفر') }})
                            </span>
                        </div>
                        <div class="h-2.5 overflow-hidden rounded-full bg-line">
                            <div class="h-full rounded-full bg-pine" style="width: {{ ((float) $row['total_hours'] / $maxComparison) * 100 }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- روند ۶ ماه اخیر --}}
        <div class="{{ $card }} p-5">
            <h3 class="mb-4 text-base font-semibold text-ink">{{ __('روند ۶ ماه اخیر') }}</h3>

            {{-- نمودار میله‌ای عمودی با CSS خالص - بدون کتابخانه‌ی بیرونی. --}}
            <div class="flex h-40 items-end justify-between gap-2">
                @foreach ($history as $point)
                    @php $h = ((float) $point['total_hours'] / $maxHistory) * 100; @endphp
                    <div class="flex flex-1 flex-col items-center gap-1.5">
                        <span class="font-mono text-[10px] text-ink/50">{{ $point['total_hours'] }}</span>
                        <div class="w-full rounded-t bg-pine/80" style="height: {{ max(2, $h) }}%" title="{{ $point['label'] }}"></div>
                        <span class="font-mono text-[10px] text-ink/40">{{ $point['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- رتبه‌بندی پرسنل (بیشترین به کمترین) --}}
        <div class="{{ $card }}">
            <h3 class="border-b border-line px-5 py-4 text-base font-semibold text-ink">{{ __('بیشترین اضافه‌کار — ۱۰ نفر اول') }}</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-start text-sm">
                    <thead>
                        <tr class="border-b border-line text-xs text-ink/50">
                            <th class="px-5 py-3 font-medium">{{ __('پرسنل') }}</th>
                            <th class="px-5 py-3 font-medium">{{ __('واحد') }}</th>
                            <th class="px-5 py-3 font-medium">{{ __('جمع ساعت') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($ranking as $row)
                            <tr class="hover:bg-surface/60">
                                <td class="px-5 py-3.5 font-medium text-ink">{{ $row['personnel_name'] }}</td>
                                <td class="px-5 py-3.5 text-xs text-ink/60">{{ $row['unit'] ?? $row['deputy'] ?? '—' }}</td>
                                <td class="px-5 py-3.5 font-mono text-xs text-ink/70">{{ $row['total_hours'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-5 py-8 text-center text-ink/40">{{ __('داده‌ای برای این دوره وجود ندارد.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- پرسنل بدون ثبت - برای پیگیری --}}
    @if ($missing->isNotEmpty())
        <div class="mt-6 {{ $card }} p-5">
            <h3 class="mb-3 text-base font-semibold text-ink">
                {{ __('پرسنل بدون ثبت اضافه‌کار') }}
                <span class="font-mono text-sm text-ink/50">({{ $missing->count() }})</span>
            </h3>
            <div class="flex flex-wrap gap-2">
                @foreach ($missing as $person)
                    <span class="rounded-lg border border-line bg-surface px-3 py-1.5 text-xs text-ink/60">
                        {{ trim($person->name . ' ' . $person->family) }}
                    </span>
                @endforeach
            </div>
        </div>
    @endif
@endsection
