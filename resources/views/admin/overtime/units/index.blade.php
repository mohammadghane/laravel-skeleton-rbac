@extends('layouts.admin')

@section('title', __('واحدهای سازمانی'))

@section('content')
    @include('admin.overtime.partials.page-header', [
        'title' => __('واحدهای سازمانی'),
        'subtitle' => __('هر واحد زیرمجموعه‌ی یک معاونت است و مدیر آن، اضافه‌کار پرسنل خود را ثبت می‌کند.'),
        'action' => new \Illuminate\Support\HtmlString(
            '<a href="' . route('admin.overtime.units.create') . '" class="open-modal inline-flex items-center gap-2 rounded-lg bg-pine px-4 py-2.5 text-sm font-medium text-white transition hover:bg-pine-600">+ ' . __('واحد جدید') . '</a>'
        ),
    ])

    {{-- فیلتر معاونت + جست‌وجو در یک فرم (فرم تودرتو در HTML مجاز نیست). --}}
    @php $control = 'rounded-lg border border-line bg-white px-3 py-2 text-sm focus:border-pine focus:outline-none focus:ring-2 focus:ring-pine/20'; @endphp
    <form method="GET" class="mb-4 flex flex-wrap items-center gap-2">
        <label class="text-sm text-ink/50">{{ __('معاونت') }}</label>
        <div class="w-52">
            <x-searchable-select
                name="deputy_id"
                :options="$deputies->map(fn ($d) => ['value' => $d->id, 'label' => $d->title])"
                :selected="request('deputy_id', '')"
                :placeholder="__('همه')"
                :input-class="$control . ' w-full'"
            />
        </div>

        <div class="relative">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('عنوان واحد…') }}" class="{{ $control }} w-52 ps-8">
            <span class="pointer-events-none absolute inset-y-0 start-2 flex items-center text-ink/30">⌕</span>
        </div>

        <button type="submit" class="rounded-lg border border-line px-3 py-2 text-sm font-medium text-ink/70 transition hover:bg-surface">{{ __('نمایش') }}</button>

        @if (request()->hasAny(['q', 'deputy_id']))
            <a href="{{ route('admin.overtime.units.index') }}" class="rounded-lg px-2 py-2 text-xs text-ink/50 underline hover:text-ink/70">{{ __('پاک کردن') }}</a>
        @endif
    </form>

    <div x-data="bulkDelete(
        '{{ route('admin.overtime.units.bulk-destroy') }}',
        '{{ __('حذف :count واحد انتخاب‌شده؟ واحدهایی که پرسنل دارند حذف نمی‌شوند.') }}'
    )">
        @include('admin.overtime.partials.bulk-delete', ['label' => __('واحد')])

    <div id="units-table" class="rounded-2xl border border-line bg-white shadow-card">
        @include('admin.overtime.units.partials.table', ['units' => $units])
    </div>
    </div>
@endsection

@push('scripts')
<script>
    function reloadUnitGrid() { window.location.reload(); }
</script>
@endpush
