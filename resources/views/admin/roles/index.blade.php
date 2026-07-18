@extends('layouts.admin')

@section('title', __('نقش‌های سازمانی'))

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-ink">{{ __('نقش‌های سازمانی') }}</h2>
            <p class="mt-1 text-sm text-ink/50">{{ __('مدیریت نقش‌ها و سطوح دسترسی سیستم') }}</p>
        </div>
        <a
            href="{{ route('admin.roles.create') }}"
            class="open-modal inline-flex items-center gap-2 rounded-lg bg-pine px-4 py-2.5 text-sm font-medium text-white transition hover:bg-pine-600"
        >
            + {{ __('نقش جدید') }}
        </a>
    </div>

    <div id="roles-table" class="rounded-2xl border border-line bg-white shadow-card">
        @include('admin.roles.partials.table', ['roles' => $roles])
    </div>
@endsection

@push('scripts')
<script>
function reloadRoleGrid(result) {
    window.location.reload();
}
</script>
@endpush
