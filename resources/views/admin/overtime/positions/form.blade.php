@php
    $input = 'w-full rounded-lg border border-line px-3 py-2 text-sm focus:border-pine focus:outline-none focus:ring-2 focus:ring-pine/20';
    $label = 'mb-1.5 block text-sm font-medium text-ink/70';
    $isEdit = (bool) $position?->exists;
@endphp

<h3 class="mb-4 text-base font-semibold text-ink">
    {{ $isEdit ? __('ویرایش سمت سازمانی') : __('سمت سازمانی جدید') }}
</h3>

<form
    class="js-ajax-form space-y-4"
    method="POST"
    action="{{ $isEdit ? route('admin.overtime.positions.update', $position) : route('admin.overtime.positions.store') }}"
    data-callback="reloadPositionGrid"
>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div>
        <label class="{{ $label }}">{{ __('عنوان سمت') }}</label>
        <input class="{{ $input }}" name="title" required value="{{ old('title', $position->title ?? '') }}" placeholder="{{ __('مثال: کارشناس') }}">
    </div>

    <label class="flex items-center gap-2 text-sm text-ink/70">
        <input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $position->is_active ?? true)) class="rounded border-line text-pine focus:ring-pine/20">
        {{ __('فعال') }}
    </label>

    <p class="rounded-lg border border-line bg-surface px-3 py-2 text-xs text-ink/50">
        {{ __('سقف اضافه‌کار مازاد این سمت از طریق دکمه‌ی «سقف‌ها» در فهرست تعریف می‌شود، چون به تفکیک هر ماه شمسی نسخه‌بندی می‌شود.') }}
    </p>

    <button type="submit" class="w-full rounded-lg bg-pine px-4 py-2.5 text-sm font-medium text-white hover:bg-pine-600 sm:w-auto">{{ __('ذخیره') }}</button>
</form>
