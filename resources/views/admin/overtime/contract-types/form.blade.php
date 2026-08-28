@php
    $input = 'w-full rounded-lg border border-line px-3 py-2 text-sm focus:border-pine focus:outline-none focus:ring-2 focus:ring-pine/20';
    $label = 'mb-1.5 block text-sm font-medium text-ink/70';
    $isEdit = (bool) $contractType?->exists;
@endphp

<h3 class="mb-4 text-base font-semibold text-ink">
    {{ $isEdit ? __('ویرایش نوع قرارداد') : __('نوع قرارداد جدید') }}
</h3>

<form
    class="js-ajax-form space-y-4"
    method="POST"
    action="{{ $isEdit ? route('admin.overtime.contract-types.update', $contractType) : route('admin.overtime.contract-types.store') }}"
    data-callback="reloadContractTypeGrid"
>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div>
        <label class="{{ $label }}">{{ __('عنوان نوع قرارداد') }}</label>
        <input class="{{ $input }}" name="title" required value="{{ old('title', $contractType->title ?? '') }}" placeholder="{{ __('مثال: رسمی') }}">
    </div>

    <label class="flex items-center gap-2 text-sm text-ink/70">
        <input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $contractType->is_active ?? true)) class="rounded border-line text-pine focus:ring-pine/20">
        {{ __('فعال') }}
    </label>

    <p class="rounded-lg border border-line bg-surface px-3 py-2 text-xs text-ink/50">
        {{ __('سقف اضافه‌کار، سقف کلی قرارداد و روز فعال‌سازی از طریق دکمه‌ی «سقف‌ها» در فهرست تعریف می‌شوند، چون به تفکیک هر ماه شمسی نسخه‌بندی می‌شوند.') }}
    </p>

    <button type="submit" class="w-full rounded-lg bg-pine px-4 py-2.5 text-sm font-medium text-white hover:bg-pine-600 sm:w-auto">{{ __('ذخیره') }}</button>
</form>
