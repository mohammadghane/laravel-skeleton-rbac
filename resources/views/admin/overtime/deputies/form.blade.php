{{--
    فرم ایجاد/ویرایش معاونت - داخل مودال مشترک `#admin-modal` رندر می‌شود.
    ارسال با public/js/acl-admin.js انجام می‌شود (FormData + پاسخ {success, message})،
    بنابراین id فرم باید در فهرست سلکتورهای همان فایل باشد.
--}}
@php
    $input = 'w-full rounded-lg border border-line px-3 py-2 text-sm focus:border-pine focus:outline-none focus:ring-2 focus:ring-pine/20';
    $label = 'mb-1.5 block text-sm font-medium text-ink/70';
    $isEdit = (bool) ($deputy?->exists);
@endphp

<h3 class="mb-4 text-base font-semibold text-ink">
    {{ $isEdit ? __('ویرایش معاونت') : __('معاونت جدید') }}
</h3>

<form
    class="js-ajax-form space-y-4"
    method="POST"
    action="{{ $isEdit ? route('admin.overtime.deputies.update', $deputy) : route('admin.overtime.deputies.store') }}"
    data-callback="reloadDeputyGrid"
>
    @csrf
    @if ($isEdit)
        {{-- مسیر update فعل PUT دارد ولی جاوااسکریپت مشترک پنل همیشه POST می‌فرستد؛
             method spoofing استاندارد لاراول این را حل می‌کند. --}}
        @method('PUT')
    @endif

    <div>
        <label class="{{ $label }}">{{ __('عنوان معاونت') }}</label>
        <input class="{{ $input }}" name="title" required value="{{ old('title', $deputy->title ?? '') }}" placeholder="{{ __('مثال: معاونت منابع انسانی') }}">
    </div>

    <div>
        <label class="{{ $label }}">{{ __('معاون (مسئول تایید اضافه‌کار)') }}</label>
        <select name="head_user_id" class="{{ $input }}">
            <option value="">{{ __('— تعیین نشده —') }}</option>
            @foreach ($candidates as $candidate)
                <option value="{{ $candidate->id }}" @selected((int) old('head_user_id', $deputy->head_user_id ?? 0) === $candidate->id)>
                    {{ trim($candidate->name . ' ' . $candidate->family) }} ({{ $candidate->username }})
                </option>
            @endforeach
        </select>
        <span class="mt-1 block text-xs text-ink/40">{{ __('تا زمانی که معاون تعیین نشود، مرحله‌ی تایید معاونت برای پرسنل این معاونت انجام نمی‌شود.') }}</span>
    </div>

    <label class="flex items-center gap-2 text-sm text-ink/70">
        <input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $deputy->is_active ?? true)) class="rounded border-line text-pine focus:ring-pine/20">
        {{ __('فعال') }}
    </label>

    <button type="submit" class="w-full rounded-lg bg-pine px-4 py-2.5 text-sm font-medium text-white hover:bg-pine-600 sm:w-auto">{{ __('ذخیره') }}</button>
</form>
