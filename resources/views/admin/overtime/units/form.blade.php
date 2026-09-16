@php
    $input = 'w-full rounded-lg border border-line px-3 py-2 text-sm focus:border-pine focus:outline-none focus:ring-2 focus:ring-pine/20';
    $label = 'mb-1.5 block text-sm font-medium text-ink/70';
    $isEdit = (bool) $unit?->exists;
@endphp

<h3 class="mb-4 text-base font-semibold text-ink">
    {{ $isEdit ? __('ویرایش واحد') : __('واحد جدید') }}
</h3>

<form
    class="js-ajax-form space-y-4"
    method="POST"
    action="{{ $isEdit ? route('admin.overtime.units.update', $unit) : route('admin.overtime.units.store') }}"
    data-callback="reloadUnitGrid"
>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div>
        <label class="{{ $label }}">{{ __('عنوان واحد') }}</label>
        <input class="{{ $input }}" name="title" required value="{{ old('title', $unit->title ?? '') }}" placeholder="{{ __('مثال: مدیریت توسعه نرم‌افزار') }}">
    </div>

    <div>
        <label class="{{ $label }}">{{ __('معاونت') }}</label>
        <x-searchable-select
            name="deputy_id"
            :options="$deputies->map(fn ($d) => ['value' => $d->id, 'label' => $d->title])"
            :selected="old('deputy_id', $unit->deputy_id ?? '')"
            :placeholder="__('— انتخاب کنید —')"
            :input-class="$input"
            required
        />
    </div>

    <div>
        <label class="{{ $label }}">{{ __('مدیر واحد (مسئول ثبت اضافه‌کار)') }}</label>
        <x-searchable-select
            name="manager_user_id"
            :options="$candidates->map(fn ($c) => ['value' => $c->id, 'label' => trim($c->name . ' ' . $c->family), 'hint' => '(' . $c->username . ')'])"
            :selected="old('manager_user_id', $unit->manager_user_id ?? '')"
            :placeholder="__('— تعیین نشده —')"
            :input-class="$input"
        />
        <span class="mt-1 block text-xs text-ink/40">{{ __('هر کاربر می‌تواند مدیر تنها یک واحد باشد.') }}</span>
    </div>

    <label class="flex items-center gap-2 text-sm text-ink/70">
        <input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $unit->is_active ?? true)) class="rounded border-line text-pine focus:ring-pine/20">
        {{ __('فعال') }}
    </label>

    <button type="submit" class="w-full rounded-lg bg-pine px-4 py-2.5 text-sm font-medium text-white hover:bg-pine-600 sm:w-auto">{{ __('ذخیره') }}</button>
</form>
