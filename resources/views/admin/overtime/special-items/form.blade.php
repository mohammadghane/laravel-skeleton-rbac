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
        <label class="{{ $label }}">{{ __('مسئول ثبت') }}</label>
        <select name="submitter_role" required class="{{ $input }}">
            <option value="hr" @selected(old('submitter_role', $item->submitter_role ?? 'hr') === 'hr')>{{ __('منابع انسانی — بدون نیاز به تایید') }}</option>
            <option value="unit_manager" @selected(old('submitter_role', $item->submitter_role ?? '') === 'unit_manager')>{{ __('مدیر واحد — نیازمند تایید معاونت') }}</option>
        </select>
        <span class="mt-1 block text-xs text-ink/40">{{ __('آیتم‌های منابع انسانی مستقیماً اعمال می‌شوند، حتی روی دوره‌ی نهایی‌شده.') }}</span>
    </div>

    <div>
        <label class="{{ $label }}">{{ __('مقدار پیش‌فرض (ساعت)') }}</label>
        <input class="{{ $input }} font-mono" name="default_value" type="number" step="0.01" min="0" required value="{{ old('default_value', $item->default_value ?? '0') }}">
    </div>

    <label class="flex items-center gap-2 text-sm text-ink/70">
        <input type="checkbox" name="is_variable_per_personnel" value="1" @checked((bool) old('is_variable_per_personnel', $item->is_variable_per_personnel ?? true)) class="rounded border-line text-pine focus:ring-pine/20">
        {{ __('مقدار به ازای هر پرسنل متفاوت است') }}
    </label>
    <span class="-mt-2 block text-xs text-ink/40">{{ __('اگر غیرفعال باشد، همان مقدار پیش‌فرض برای همه‌ی پرسنل واجد شرایط اعمال می‌شود.') }}</span>

    <label class="flex items-center gap-2 text-sm text-ink/70">
        <input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $item->is_active ?? true)) class="rounded border-line text-pine focus:ring-pine/20">
        {{ __('فعال') }}
    </label>

    <button type="submit" class="w-full rounded-lg bg-pine px-4 py-2.5 text-sm font-medium text-white hover:bg-pine-600 sm:w-auto">{{ __('ذخیره') }}</button>
</form>
