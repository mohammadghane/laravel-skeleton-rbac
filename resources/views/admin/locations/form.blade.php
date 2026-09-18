@php
    $input = 'w-full rounded-lg border border-line px-3 py-2 text-sm focus:border-pine focus:outline-none focus:ring-2 focus:ring-pine/20';
    $label = 'mb-1.5 block text-sm font-medium text-ink/70';
@endphp

<h3 class="mb-4 text-base font-semibold text-ink">
    {{ $location?->exists ? __('ویرایش موقعیت') : __('ثبت موقعیت جدید') }}
</h3>

<form
    class="js-ajax-form space-y-4"
    method="POST"
    action="{{ $location?->exists ? route('admin.locations.update', $location) : route('admin.locations.store') }}"
    data-callback="reloadLocationGrid"
>
    @csrf
    @if ($location?->exists)
        @method('PUT')
    @endif

    <div
        x-data="locationPicker({
            lat: @js($location?->latitude !== null ? (float) $location->latitude : null),
            lng: @js($location?->longitude !== null ? (float) $location->longitude : null),
            fallback: {
                lat: {{ (float) config('services.map.default_lat') }},
                lng: {{ (float) config('services.map.default_lng') }},
                zoom: {{ (int) config('services.map.default_zoom') }},
            },
        })"
    >
        <div>
            <label class="{{ $label }}">{{ __('عنوان موقعیت') }}</label>
            <input class="{{ $input }}" name="title" required value="{{ old('title', $location->title ?? '') }}">
        </div>

        <div class="mt-4">
            <label class="{{ $label }}">{{ __('انتخاب نقطه روی نقشه') }}</label>

            {{-- مختصات با input مخفی می‌رود تا اعتبارسنجی سمت سرور دست‌نخورده بماند. --}}
            <input type="hidden" name="latitude" :value="lat">
            <input type="hidden" name="longitude" :value="lng">

            <div
                x-ref="map"
                class="h-72 w-full rounded-lg border border-line"
                style="background: #eef0ec;"
            ></div>

            <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                <p class="text-xs text-ink/40">
                    {{ __('روی نقشه کلیک کنید تا نقطه مشخص شود. نشانگر را هم می‌توانید بکشید.') }}
                </p>

                <template x-if="hasPoint()">
                    <span class="rounded-lg border border-line bg-surface px-2.5 py-1 font-mono text-xs text-ink/60" dir="ltr">
                        <span x-text="lat"></span>, <span x-text="lng"></span>
                    </span>
                </template>
            </div>

            <template x-if="!hasPoint()">
                <p class="mt-2 rounded-lg border border-gold/40 bg-gold-100 px-3 py-2 text-xs text-ink/70">
                    {{ __('هنوز نقطه‌ای انتخاب نشده است.') }}
                </p>
            </template>
        </div>
    </div>

    <div>
        <label class="{{ $label }}">{{ __('توضیحات') }}</label>
        <textarea class="{{ $input }}" name="description" rows="2">{{ old('description', $location->description ?? '') }}</textarea>
    </div>

    <label class="flex items-center gap-2 text-sm text-ink/70">
        <input type="checkbox" name="is_active" value="1" class="rounded border-line text-pine focus:ring-pine"
            @checked(old('is_active', $location->is_active ?? true))>
        {{ __('فعال') }}
    </label>

    <button type="submit" class="w-full rounded-lg bg-pine px-4 py-2.5 text-sm font-medium text-white hover:bg-pine-600 sm:w-auto">
        {{ __('ذخیره') }}
    </button>
</form>
