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
            'placeholder' => __('نام، کد پرسنلی، موبایل یا کد ملی…'),
        ])
    </div>

    <div id="users-table" class="rounded-2xl border border-line bg-white shadow-card">
        @include('admin.users.partials.table', ['users' => $users])
    </div>
@endsection

@push('scripts')
<script>
function reloadUserGrid(result) {
    window.location.reload();
}
</script>
@endpush
