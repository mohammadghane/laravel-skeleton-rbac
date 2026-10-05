@extends('layouts.admin')

@section('title', __('پارامترهای مالی'))

@section('content')
    @php
        $input = 'w-full rounded-lg border border-line px-3 py-2 text-sm focus:border-pine focus:outline-none focus:ring-2 focus:ring-pine/20';
        $card = 'rounded-2xl border border-line bg-white p-5 shadow-card';
    @endphp

    @include('admin.overtime.partials.page-header', [
        'title' => __('پارامترهای مالی'),
        'subtitle' => __('ثبت ضریب ریالی و تعداد روزهای کارکرد برای پرسنل'),
        'action' => null,
    ])

    <div class="{{ $card }}">
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <form class="flex gap-2" method="GET">
                <input type="hidden" name="jalali_year" value="{{ $year }}">
                <input type="hidden" name="jalali_month" value="{{ $month }}">

                <div class="flex-1">
                    <label class="mb-1 block text-sm font-medium text-ink/70">{{ __('جست‌وجو') }}</label>
                    <input class="{{ $input }}" name="q" type="text" value="{{ request('q') }}" placeholder="{{ __('نام، کد پرسنلی...') }}">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-ink/70">{{ __('ماه') }}</label>
                    <input class="{{ $input }}" name="jalali_year" type="number" value="{{ $year }}" min="1390" max="1450" style="max-width: 80px;">
                    <input class="{{ $input }}" name="jalali_month" type="number" value="{{ $month }}" min="1" max="12" style="max-width: 50px;">
                </div>

                <button type="submit" class="rounded-lg bg-pine px-4 py-2.5 text-sm font-medium text-white hover:bg-pine-600">
                    {{ __('جست‌وجو') }}
                </button>
            </form>
        </div>

        <div class="mb-4 rounded-xl border border-line bg-pine-50 p-4">
            <h4 class="mb-3 text-sm font-semibold text-pine">{{ __('ثبت گروهی پارامترهای مالی') }}</h4>
            <p class="mb-3 text-xs text-pine/70">
                {{ __('ثبت گروهی پارامترهای مالی برای پرسنل انتخاب‌شده یا همه پرسنل فهرست') }}
            </p>
            <form class="js-ajax-form grid grid-cols-1 gap-3 sm:grid-cols-3" method="POST" action="{{ route('admin.overtime.financial-params.bulk-store') }}">
                @csrf
                <input type="hidden" name="jalali_year" value="{{ $year }}">
                <input type="hidden" name="jalali_month" value="{{ $month }}">
                <input type="hidden" name="personnel_ids" id="bulk-personnel-ids" value="">

                <div>
                    <label class="mb-1 block text-sm font-medium text-ink/70">{{ __('ضریب ریالی') }}</label>
                    <input class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:border-pine focus:outline-none focus:ring-2 focus:ring-pine/20" name="rial_coefficient" type="number" step="0.01" min="0" placeholder="{{ __('مثال: ۲۵۰۰۰۰') }}">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-ink/70">{{ __('تعداد روزهای کارکرد') }}</label>
                    <input class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:border-pine focus:outline-none focus:ring-2 focus:ring-pine/20" name="worked_days_count" type="number" step="1" min="0" max="31" placeholder="{{ __('مثال: ۳۰') }}">
                </div>

                <div class="flex items-end">
                    <button type="submit" class="w-full rounded-lg bg-pine px-4 py-2.5 text-sm font-medium text-white hover:bg-pine-600">
                        {{ __('ثبت گروهی') }}
                    </button>
                </div>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-start text-sm">
                <thead>
                    <tr class="border-b border-line text-xs text-ink/50">
                        <th class="px-5 py-3">
                            <input type="checkbox" id="select-all" class="h-4 w-4 rounded border-ink/30 text-pine focus:ring-pine/20">
                        </th>
                        <th class="px-5 py-3 font-medium">{{ __('پرسنل') }}</th>
                        <th class="px-5 py-3 font-medium">{{ __('واحد / معاونت') }}</th>
                        <th class="px-5 py-3 font-medium">{{ __('نوع قرارداد') }}</th>
                        <th class="px-5 py-3 font-medium">{{ __('ضریب ریالی') }}</th>
                        <th class="px-5 py-3 font-medium">{{ __('روز کارکرد') }}</th>
                        <th class="px-5 py-3 font-medium">{{ __('عملیات') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($personnel as $p)
                        <tr class="hover:bg-surface/60">
                            <td class="px-5 py-3.5">
                                <input type="checkbox" class="row-select h-4 w-4 rounded border-ink/30 text-pine focus:ring-pine/20" value="{{ $p->id }}">
                            </td>
                            <td class="px-5 py-3.5 font-medium text-ink">
                                {{ trim($p->name . ' ' . $p->family) }}
                                <div class="text-xs text-ink/50">{{ $p->username }}</div>
                            </td>
                            <td class="px-5 py-3.5 text-xs text-ink/60">
                                {{ $p->unit?->title ?? $p->deputy?->title ?? '—' }}
                            </td>
                            <td class="px-5 py-3.5 text-xs text-ink/60">
                                {{ $p->contractType?->title ?? '—' }}
                            </td>
                            <td class="px-5 py-3.5 font-mono text-xs text-ink/70">
                                {{ $periods->get($p->id)?->rial_coefficient ?? '—' }}
                            </td>
                            <td class="px-5 py-3.5 font-mono text-xs text-ink/70">
                                {{ $periods->get($p->id)?->worked_days_count ?? '—' }}
                            </td>
                            <td class="px-5 py-3.5">
                                <a href="{{ route('admin.overtime.financial-params.edit', $p->id) }}?jalali_year={{ $year }}&jalali_month={{ $month }}" class="open-modal rounded-lg border border-pine bg-pine-100 px-3 py-1.5 text-xs font-medium text-pine hover:bg-pine/20">
                                    {{ __('ویرایش') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-10 text-center text-ink/40">{{ __('هیچ پرسنلی یافت نشد.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $personnel->links() }}
        </div>
    </div>
@endsection

@push('scripts')
<script>
    // انتخاب/لغو انتخاب همه
    const selectAll = document.querySelector('#select-all');
    const rowSelects = document.querySelectorAll('.row-select');
    
    if (selectAll && rowSelects.length > 0) {
        selectAll.addEventListener('change', function () {
            rowSelects.forEach(cb => cb.checked = this.checked);
            updateBulkForm();
        });
        
        rowSelects.forEach(cb => {
            cb.addEventListener('change', updateBulkForm);
        });
        
        function updateBulkForm() {
            const selectedIds = Array.from(rowSelects)
                .filter(cb => cb.checked)
                .map(cb => cb.value);
            
            document.querySelector('#bulk-personnel-ids').value = JSON.stringify(selectedIds);
        }
    }
</script>
@endpush
