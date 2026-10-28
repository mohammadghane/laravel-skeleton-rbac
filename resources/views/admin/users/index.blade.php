@extends('layouts.admin')

@section('title', __('پرسنل'))

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-ink">{{ __('پرسنل') }}</h2>
            <p class="mt-1 text-sm text-ink/50">{{ __('مدیریت پرسنل، جایگاه سازمانی و نقش‌های دسترسی') }}</p>
        </div>
        <a
            href="{{ route('admin.users.create') }}"
            class="open-modal inline-flex items-center gap-2 rounded-lg bg-pine px-4 py-2.5 text-sm font-medium text-white transition hover:bg-pine-600"
        >
            + {{ __('پرسنل جدید') }}
        </a>
    </div>

    <div class="mb-4">
        @include('admin.overtime.partials.search-box', [
            'route' => 'admin.users.index',
            'placeholder' => __('نام، کد پرسنلی، موبایل، سمت یا معاونت…'),
            // تا جست‌وجو انتخابِ «تعداد در هر صفحه» را صفر نکند.
            'extra' => [
                'per_page' => request('per_page'),
                'filters[deputy_id]' => request('filters.deputy_id'),
                'filters[unit_id]' => request('filters.unit_id'),
            ],
        ])
    </div>

    <form method="GET" action="{{ route('admin.users.index') }}" class="mb-4 flex flex-wrap items-end gap-3 rounded-2xl border border-line bg-white p-4 shadow-card">
        <input type="hidden" name="q" value="{{ request('q') }}">
        <input type="hidden" name="per_page" value="{{ request('per_page') }}">

        <div class="min-w-48">
            <label for="filter-deputy" class="mb-1 block text-xs font-medium text-ink/60">{{ __('معاونت') }}</label>
            <select id="filter-deputy" name="filters[deputy_id]" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm">
                <option value="">{{ __('همه‌ی معاونت‌ها') }}</option>
                @foreach ($deputies as $deputy)
                    <option value="{{ $deputy->id }}" @selected((string) request('filters.deputy_id') === (string) $deputy->id)>{{ $deputy->title }}</option>
                @endforeach
            </select>
        </div>

        <div class="min-w-48">
            <label for="filter-unit" class="mb-1 block text-xs font-medium text-ink/60">{{ __('واحد سازمانی') }}</label>
            <select id="filter-unit" name="filters[unit_id]" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm">
                <option value="">{{ __('همه‌ی واحدها') }}</option>
                @foreach ($units as $unit)
                    <option value="{{ $unit->id }}" @selected((string) request('filters.unit_id') === (string) $unit->id)>{{ $unit->title }}</option>
                @endforeach
            </select>
        </div>

        <button type="submit" class="rounded-lg bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-pine-600">{{ __('اعمال فیلتر') }}</button>
        @if (request()->filled('filters.deputy_id') || request()->filled('filters.unit_id'))
            <a href="{{ route('admin.users.index', array_filter(['q' => request('q'), 'per_page' => request('per_page')])) }}" class="rounded-lg border border-line px-4 py-2 text-sm text-ink/60 hover:bg-surface">{{ __('پاک کردن فیلتر') }}</a>
        @endif
    </form>

    <div x-data="bulkDelete(
        '{{ route('admin.users.bulk-destroy') }}',
        '{{ __('حذف :count پرسنل انتخاب‌شده؟ مدیران واحد و معاونان حذف نمی‌شوند.') }}'
    )">
        @include('admin.overtime.partials.bulk-delete', ['label' => __('پرسنل')])

    <div id="users-table" class="rounded-2xl border border-line bg-white shadow-card">
        @include('admin.users.partials.table', ['users' => $users])
    </div>
    </div>
@endsection

@push('scripts')
<script>
function reloadUserGrid(result) {
    window.location.reload();
}
</script>
@endpush
