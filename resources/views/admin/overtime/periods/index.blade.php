@extends('layouts.admin')

@section('title', __('ثبت و تایید اضافه‌کار'))

@section('content')
    @php
        $roleLabels = [
            'manager' => __('مدیر واحد — ثبت اضافه‌کار عادی'),
            'deputy' => __('معاونت — بررسی، اضافه‌کار مازاد و تایید'),
            'ceo' => __('مدیرعامل — تایید نهایی'),
            'hr' => __('منابع انسانی — مشاهده و پارامترهای مالی'),
            'self' => __('اضافه‌کار من'),
        ];
    @endphp

    @include('admin.overtime.partials.page-header', [
        'title' => __('ثبت و تایید اضافه‌کار'),
        'subtitle' => $scopeTitle . ' — ' . ($roleLabels[$role] ?? ''),
    ])

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-3">
            @include('admin.overtime.partials.month-filter', [
                'year' => $year, 'month' => $month, 'route' => 'admin.overtime.periods.index',
                'extra' => ['q' => request('q')],
            ])

            @include('admin.overtime.partials.search-box', [
                'route' => 'admin.overtime.periods.index',
                'placeholder' => __('نام یا کد پرسنلی…'),
                'extra' => ['jalali_year' => $year, 'jalali_month' => $month],
            ])
        </div>

        <span class="rounded-lg border border-line bg-white px-3 py-2 text-xs text-ink/50">
            {{ __('دوره‌ی جاری') }}: <span class="font-mono text-ink/70">{{ \App\Support\Jalali::periodLabel($year, $month) }}</span>
        </span>
    </div>

    <div class="rounded-2xl border border-line bg-white shadow-card">
        <div class="overflow-x-auto">
            <table class="w-full text-start text-sm">
                <thead>
                    <tr class="border-b border-line text-xs text-ink/50">
                        <th class="px-5 py-3 font-medium">{{ __('پرسنل') }}</th>
                        <th class="px-5 py-3 font-medium">{{ __('نوع قرارداد') }}</th>
                        <th class="px-5 py-3 font-medium">{{ __('عادی') }}</th>
                        <th class="px-5 py-3 font-medium">{{ __('مازاد') }}</th>
                        <th class="px-5 py-3 font-medium">{{ __('ویژه') }}</th>
                        <th class="px-5 py-3 font-medium">{{ __('جمع') }}</th>
                        <th class="px-5 py-3 font-medium">{{ __('قابل پرداخت') }}</th>
                        <th class="px-5 py-3 font-medium">{{ __('وضعیت') }}</th>
                        <th class="px-5 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($personnel as $person)
                        @php
                            $period = $periods->get($person->id);
                            $specialHours = $period?->specialItemLines->sum('hours') ?? 0;
                            $exceeds = $period?->exceedsOverallCeiling() ?? false;
                        @endphp
                        <tr class="hover:bg-surface/60">
                            <td class="px-5 py-3.5">
                                <div class="font-medium text-ink">{{ trim($person->name . ' ' . $person->family) }}</div>
                                <div class="font-mono text-xs text-ink/40">{{ $person->username }}</div>
                            </td>

                            <td class="px-5 py-3.5 text-xs text-ink/60">
                                {{ $person->contractType?->title ?? '—' }}
                                @if ($person->position)
                                    <div class="text-ink/40">{{ $person->position->title }}</div>
                                @endif
                            </td>

                            <td class="px-5 py-3.5 font-mono text-xs text-ink/70">{{ $period?->normal_hours ?? '—' }}</td>
                            <td class="px-5 py-3.5 font-mono text-xs text-ink/70">{{ $period?->excess_hours ?? '—' }}</td>
                            <td class="px-5 py-3.5 font-mono text-xs text-ink/70">{{ $period ? number_format((float) $specialHours, 2) : '—' }}</td>

                            <td class="px-5 py-3.5 font-mono text-xs font-medium text-ink">{{ $period?->totalHours() ?? '—' }}</td>

                            <td class="px-5 py-3.5">
                                @if ($period)
                                    {{-- بند ۴.۲: اگر مجموع از سقف کلی قرارداد بگذرد، عدد ارسالی به مالی
                                         همان سقف است. اختلاف را صریح نشان می‌دهیم تا کاربر غافلگیر نشود. --}}
                                    <span class="font-mono text-xs font-medium {{ $exceeds ? 'text-brick' : 'text-pine' }}">{{ $period->payableHours() }}</span>
                                    @if ($exceeds)
                                        <div class="text-[11px] text-brick">{{ __('کسر') }} {{ $period->cappedHours() }} {{ __('ساعت') }}</div>
                                    @endif
                                @else
                                    <span class="text-ink/30">—</span>
                                @endif
                            </td>

                            <td class="px-5 py-3.5">
                                @if ($period)
                                    @include('admin.overtime.partials.status-badge', ['status' => $period->status])
                                @else
                                    <span class="rounded-full border border-line bg-surface px-2.5 py-1 text-xs text-ink/40">{{ __('ثبت نشده') }}</span>
                                @endif
                            </td>

                            <td class="px-5 py-3.5">
                                <div class="flex justify-end gap-2">
                                    <a
                                        href="{{ route('admin.overtime.periods.show', ['personnel' => $person->id, 'jalali_year' => $year, 'jalali_month' => $month]) }}"
                                        class="rounded-lg border border-line px-3 py-1.5 text-xs font-medium text-ink/70 hover:bg-surface"
                                    >
                                        {{ $role === 'self' || $role === 'hr' ? __('جزئیات') : __('مدیریت') }}
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-5 py-10 text-center text-ink/40">{{ __('پرسنلی در محدوده‌ی شما یافت نشد.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
