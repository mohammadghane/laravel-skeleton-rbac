@extends('layouts.admin')

@section('title', __('واگذاری ظرفیت مازاد'))

@section('content')
    @php
        $input = 'w-full rounded-lg border border-line px-3 py-2 text-sm focus:border-pine focus:outline-none focus:ring-2 focus:ring-pine/20';
        $label = 'mb-1.5 block text-sm font-medium text-ink/70';
        $card = 'rounded-2xl border border-line bg-white shadow-card';
    @endphp

    @include('admin.overtime.partials.page-header', [
        'title' => __('واگذاری ظرفیت اضافه‌کار مازاد'),
        'subtitle' => __('معاونتی که از تمام ظرفیت مازاد خود استفاده نکرده، می‌تواند باقی‌مانده را به معاونت دیگری واگذار کند.'),
    ])

    <div class="mb-4">
        @include('admin.overtime.partials.month-filter', [
            'year' => $year, 'month' => $month, 'route' => 'admin.overtime.capacity-transfers.index',
        ])
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- فرم ثبت واگذاری --}}
        <div class="lg:col-span-1">
            <div class="{{ $card }} p-5">
                <h3 class="mb-4 text-base font-semibold text-ink">{{ __('واگذاری جدید') }}</h3>

                <form class="js-ajax-form space-y-4" method="POST" action="{{ route('admin.overtime.capacity-transfers.store') }}" data-callback="reloadTransfers">
                    @csrf
                    <input type="hidden" name="jalali_year" value="{{ $year }}">
                    <input type="hidden" name="jalali_month" value="{{ $month }}">

                    <div>
                        <label class="{{ $label }}">{{ __('از معاونت') }}</label>
                        @if ($isHr)
                            <select name="from_deputy_id" required class="{{ $input }}">
                                <option value="">{{ __('— انتخاب کنید —') }}</option>
                                @foreach ($deputies as $deputy)
                                    <option value="{{ $deputy->id }}">
                                        {{ $deputy->title }} ({{ __('باقی‌مانده') }}: {{ $capacity[$deputy->id]['remaining'] }})
                                    </option>
                                @endforeach
                            </select>
                        @else
                            {{-- معاون فقط از معاونت خودش می‌تواند واگذار کند. --}}
                            <input type="hidden" name="from_deputy_id" value="{{ $ownDeputy->id }}">
                            <div class="rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink/70">
                                {{ $ownDeputy->title }}
                                <span class="font-mono text-xs text-ink/50">
                                    ({{ __('باقی‌مانده') }}: {{ $capacity[$ownDeputy->id]['remaining'] }})
                                </span>
                            </div>
                        @endif
                    </div>

                    <div>
                        <label class="{{ $label }}">{{ __('به معاونت') }}</label>
                        <select name="to_deputy_id" required class="{{ $input }}">
                            <option value="">{{ __('— انتخاب کنید —') }}</option>
                            @foreach ($deputies as $deputy)
                                @if ($isHr || $deputy->id !== $ownDeputy?->id)
                                    <option value="{{ $deputy->id }}">{{ $deputy->title }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="{{ $label }}">{{ __('ساعت') }}</label>
                        <input class="{{ $input }} font-mono" name="hours" type="number" step="0.01" min="0.01" required>
                        <span class="mt-1 block text-xs text-ink/40">{{ __('نمی‌تواند از ظرفیت باقی‌مانده‌ی معاونت مبدأ بیشتر باشد.') }}</span>
                    </div>

                    <div>
                        <label class="{{ $label }}">{{ __('توضیح (اختیاری)') }}</label>
                        <input class="{{ $input }}" name="note" maxlength="190">
                    </div>

                    <button type="submit" class="w-full rounded-lg bg-pine px-4 py-2.5 text-sm font-medium text-white hover:bg-pine-600">{{ __('ثبت واگذاری') }}</button>
                </form>
            </div>
        </div>

        <div class="space-y-6 lg:col-span-2">
            {{-- وضعیت ظرفیت هر معاونت --}}
            <div class="{{ $card }}">
                <h3 class="border-b border-line px-5 py-4 text-base font-semibold text-ink">{{ __('ظرفیت مازاد معاونت‌ها') }}</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-start text-sm">
                        <thead>
                            <tr class="border-b border-line text-xs text-ink/50">
                                <th class="px-5 py-3 font-medium">{{ __('معاونت') }}</th>
                                <th class="px-5 py-3 font-medium">{{ __('ظرفیت پایه') }}</th>
                                <th class="px-5 py-3 font-medium">{{ __('واگذارشده') }}</th>
                                <th class="px-5 py-3 font-medium">{{ __('دریافت‌شده') }}</th>
                                <th class="px-5 py-3 font-medium">{{ __('ظرفیت مؤثر') }}</th>
                                <th class="px-5 py-3 font-medium">{{ __('باقی‌مانده') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @forelse ($deputies as $deputy)
                                @php
                                    $c = $capacity[$deputy->id];
                                    $effective = bcadd(bcsub($c['base'], $c['granted'], 2), $c['received'], 2);
                                @endphp
                                <tr class="hover:bg-surface/60">
                                    <td class="px-5 py-3.5 font-medium text-ink">{{ $deputy->title }}</td>
                                    <td class="px-5 py-3.5 font-mono text-xs text-ink/60">{{ $c['base'] }}</td>
                                    <td class="px-5 py-3.5 font-mono text-xs {{ bccomp($c['granted'], '0', 2) === 1 ? 'text-brick' : 'text-ink/30' }}">
                                        {{ bccomp($c['granted'], '0', 2) === 1 ? '−' . $c['granted'] : '—' }}
                                    </td>
                                    <td class="px-5 py-3.5 font-mono text-xs {{ bccomp($c['received'], '0', 2) === 1 ? 'text-pine' : 'text-ink/30' }}">
                                        {{ bccomp($c['received'], '0', 2) === 1 ? '+' . $c['received'] : '—' }}
                                    </td>
                                    <td class="px-5 py-3.5 font-mono text-xs font-medium text-ink">{{ $effective }}</td>
                                    <td class="px-5 py-3.5 font-mono text-xs font-medium {{ bccomp($c['remaining'], '0', 2) === 0 ? 'text-brick' : 'text-pine' }}">{{ $c['remaining'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-5 py-8 text-center text-ink/40">{{ __('معاونتی تعریف نشده است.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- فهرست واگذاری‌های این ماه --}}
            <div class="{{ $card }}">
                <h3 class="border-b border-line px-5 py-4 text-base font-semibold text-ink">
                    {{ __('واگذاری‌های دوره‌ی') }} <span class="font-mono">{{ \App\Support\Jalali::periodLabel($year, $month) }}</span>
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-start text-sm">
                        <thead>
                            <tr class="border-b border-line text-xs text-ink/50">
                                <th class="px-5 py-3 font-medium">{{ __('از') }}</th>
                                <th class="px-5 py-3 font-medium">{{ __('به') }}</th>
                                <th class="px-5 py-3 font-medium">{{ __('ساعت') }}</th>
                                <th class="px-5 py-3 font-medium">{{ __('توضیح') }}</th>
                                <th class="px-5 py-3 font-medium">{{ __('ثبت‌کننده') }}</th>
                                <th class="px-5 py-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @forelse ($transfers as $transfer)
                                <tr class="hover:bg-surface/60">
                                    <td class="px-5 py-3.5 text-ink/70">{{ $transfer->fromDeputy?->title ?? '—' }}</td>
                                    <td class="px-5 py-3.5 text-ink/70">{{ $transfer->toDeputy?->title ?? '—' }}</td>
                                    <td class="px-5 py-3.5 font-mono text-xs font-medium text-ink">{{ $transfer->hours }}</td>
                                    <td class="px-5 py-3.5 text-xs text-ink/50">{{ $transfer->note ?: '—' }}</td>
                                    <td class="px-5 py-3.5 text-xs text-ink/50">
                                        {{ $transfer->creator ? trim($transfer->creator->name . ' ' . $transfer->creator->family) : '—' }}
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <div class="flex justify-end">
                                            <button class="btn-remove rounded-lg border border-brick/20 bg-brick-100 px-3 py-1.5 text-xs font-medium text-brick hover:bg-brick/10" data-url="{{ route('admin.overtime.capacity-transfers.destroy', $transfer) }}">{{ __('حذف') }}</button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-5 py-8 text-center text-ink/40">{{ __('برای این دوره واگذاری‌ای ثبت نشده است.') }}</td></tr>
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
    function reloadTransfers() { window.location.reload(); }
</script>
@endpush
