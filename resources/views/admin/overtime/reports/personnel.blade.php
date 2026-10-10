@extends('layouts.admin')

@section('title', __('گزارش ماهانه‌ی پرسنل'))

@section('content')
    @include('admin.overtime.partials.page-header', [
        'title' => __('گزارش ماهانه‌ی اضافه‌کار پرسنل'),
        'subtitle' => __('ریز اضافه‌کار هر پرسنل در محدوده‌ی دسترسی شما — دوره‌ی') . ' ' . \App\Support\Jalali::periodLabel($year, $month),
    ])

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-3">
            @include('admin.overtime.partials.month-filter', [
                'year' => $year, 'month' => $month, 'route' => 'admin.overtime.reports.personnel-monthly',
                'extra' => ['q' => request('q')],
            ])

            @include('admin.overtime.partials.search-box', [
                'route' => 'admin.overtime.reports.personnel-monthly',
                'placeholder' => __('نام، واحد یا سمت…'),
                'extra' => ['jalali_year' => $year, 'jalali_month' => $month],
            ])
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.overtime.dashboard', ['jalali_year' => $year, 'jalali_month' => $month]) }}" class="rounded-lg border border-line px-4 py-2.5 text-sm font-medium text-ink/70 transition hover:bg-surface">
                {{ __('بازگشت به داشبورد') }}
            </a>
            @if ($canExport)
                <a href="{{ route('admin.overtime.reports.export', ['jalali_year' => $year, 'jalali_month' => $month]) }}" class="rounded-lg bg-pine px-4 py-2.5 text-sm font-medium text-white transition hover:bg-pine-600">
                    {{ __('خروجی اکسل مالی') }}
                </a>
            @endif
        </div>
    </div>

    <div class="rounded-2xl border border-line bg-white shadow-card">
        <div class="overflow-x-auto">
            <table class="w-full text-start text-sm">
                <thead>
                    <tr class="border-b border-line text-xs text-ink/50">
                        <th class="px-5 py-3 font-medium">{{ __('پرسنل') }}</th>
                        <th class="px-5 py-3 font-medium">{{ __('واحد / معاونت') }}</th>
                        <th class="px-5 py-3 font-medium">{{ __('نوع قرارداد') }}</th>
                        <th class="px-5 py-3 font-medium">{{ __('عادی') }}</th>
                        <th class="px-5 py-3 font-medium">{{ __('مازاد') }}</th>
                        <th class="px-5 py-3 font-medium">{{ __('ویژه') }}</th>
                        <th class="px-5 py-3 font-medium">{{ __('جمع') }}</th>
                        <th class="px-5 py-3 font-medium">{{ __('وضعیت') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($rows as $row)
                        <tr class="hover:bg-surface/60">
                            <td class="px-5 py-3.5 font-medium text-ink">{{ $row['personnel_name'] }}</td>
                            <td class="px-5 py-3.5 text-xs text-ink/60">{{ $row['unit'] ?? $row['deputy'] ?? '—' }}</td>
                            <td class="px-5 py-3.5 text-xs text-ink/60">{{ $row['contract_type'] ?? '—' }}</td>
                            <td class="px-5 py-3.5 font-mono text-xs text-ink/70">{{ $row['normal_hours'] }}</td>
                            <td class="px-5 py-3.5 font-mono text-xs text-ink/70">{{ $row['excess_hours'] }}</td>
                            <td class="px-5 py-3.5 font-mono text-xs text-ink/70">
                                {{ $row['special_hours'] }}
                                @foreach ($row['special_items'] as $item)
                                    <div class="mt-1 text-[11px] text-ink/50">{{ $item['title'] }}: {{ $item['hours'] }}</div>
                                @endforeach
                            </td>
                            <td class="px-5 py-3.5 font-mono text-xs font-medium text-ink">{{ $row['total_hours'] }}</td>
                            <td class="px-5 py-3.5">@include('admin.overtime.partials.status-badge', ['status' => $row['status']])</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-5 py-10 text-center text-ink/40">{{ __('برای این دوره اضافه‌کاری ثبت نشده است.') }}</td></tr>
                    @endforelse
                </tbody>

                @if ($rows->isNotEmpty())
                    <tfoot>
                        <tr class="border-t border-line bg-surface/60 text-sm font-medium">
                            <td class="px-5 py-3 text-ink/70" colspan="3">{{ __('جمع کل') }}</td>
                            <td class="px-5 py-3 font-mono text-xs text-ink">{{ number_format((float) $rows->sum(fn ($r) => (float) $r['normal_hours']), 2) }}</td>
                            <td class="px-5 py-3 font-mono text-xs text-ink">{{ number_format((float) $rows->sum(fn ($r) => (float) $r['excess_hours']), 2) }}</td>
                            <td class="px-5 py-3 font-mono text-xs text-ink">{{ number_format((float) $rows->sum(fn ($r) => (float) $r['special_hours']), 2) }}</td>
                            <td class="px-5 py-3 font-mono text-xs text-ink">{{ number_format((float) $rows->sum(fn ($r) => (float) $r['total_hours']), 2) }}</td>
                            <td class="px-5 py-3"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
@endsection
