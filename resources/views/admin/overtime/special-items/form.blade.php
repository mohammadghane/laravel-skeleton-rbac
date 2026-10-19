@php
    $input = 'w-full rounded-lg border border-line px-3 py-2 text-sm focus:border-pine focus:outline-none focus:ring-2 focus:ring-pine/20';
    $label = 'mb-1.5 block text-sm font-medium text-ink/70';
    $isEdit = (bool) $item?->exists;
@endphp

<h3 class="mb-4 text-base font-semibold text-ink">
    {{ $isEdit ? __('ویرایش آیتم ویژه') : __('آیتم ویژه‌ی جدید') }}
</h3>

<form
    class="js-ajax-form space-y-4"
    method="POST"
    action="{{ $isEdit ? route('admin.overtime.special-items.update', $item) : route('admin.overtime.special-items.store') }}"
    data-callback="reloadSpecialItemGrid"
>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div>
        <label class="{{ $label }}">{{ __('عنوان آیتم') }}</label>
        <input class="{{ $input }}" name="title" required value="{{ old('title', $item->title ?? '') }}" placeholder="{{ __('مثال: ایثارگری') }}">
    </div>

    <div>
        <label class="{{ $label }}">{{ __('مقدار ثابت برای هر پرسنل مشمول') }}</label>
        <input class="{{ $input }} font-mono" name="default_value" type="number" step="1" min="0" required value="{{ old('default_value', $item->default_value ?? '0') }}">
    </div>

    <div>
        <label class="{{ $label }}">{{ __('واحد آیتم') }}</label>
        <select class="{{ $input }}" name="unit" required>
            <option value="hour" @selected(old('unit', $item->unit ?? 'hour') === 'hour')>{{ __('ساعت') }}</option>
            <option value="day" @selected(old('unit', $item->unit ?? 'hour') === 'day')>{{ __('روز') }}</option>
        </select>
    </div>

    <label class="flex items-center gap-2 text-sm text-ink/70">
        <input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $item->is_active ?? true)) class="rounded border-line text-pine focus:ring-pine/20">
        {{ __('فعال') }}
    </label>

    <button type="submit" class="w-full rounded-lg bg-pine px-4 py-2.5 text-sm font-medium text-white hover:bg-pine-600 sm:w-auto">{{ __('ذخیره') }}</button>
</form>
