@php
    $input = 'w-full rounded-lg border border-line px-3 py-2 text-sm focus:border-pine focus:outline-none focus:ring-2 focus:ring-pine/20';
    $label = 'mb-1.5 block text-sm font-medium text-ink/70';
@endphp

<h3 class="mb-4 text-base font-semibold text-ink">{{ __('ثبت نظر') }}</h3>

{{-- محدوده‌های مجاز برای رسم دایره‌ها؛ فقط راهنمای بصری است. --}}
<script>window.allowedZones = @json($zones ?? []);</script>

<form
    class="js-ajax-form space-y-4"
    method="POST"
    action="{{ route('admin.location-comments.store') }}"
    data-callback="reloadCommentGrid"
    x-data="commentPicker({
        checkUrl: '{{ route('admin.location-comments.check') }}',
        fallback: {
            lat: {{ (float) config('services.map.default_lat') }},
            lng: {{ (float) config('services.map.default_lng') }},
            zoom: {{ (int) config('services.map.default_zoom') }},
        },
    })"
>
    @csrf

    <div>
        <label class="{{ $label }}">{{ __('انتخاب نقطه روی نقشه') }}</label>

        <input type="hidden" name="latitude" :value="lat">
        <input type="hidden" name="longitude" :value="lng">

        <div x-ref="map" class="h-72 w-full rounded-lg border border-line" style="background: #eef0ec;"></div>

        <p class="mt-2 text-xs text-ink/40">
            {{ __('روی نقشه کلیک کنید. دایره‌های سبز محدوده‌های مجاز هستند.') }}
        </p>
    </div>

    {{-- نتیجه‌ی بررسی: تنها چیزی که کاربر واقعاً باید ببیند. --}}
    <template x-if="checked">
        <div
            class="rounded-lg border px-3 py-2.5 text-sm"
            :class="allowed
                ? 'border-pine/30 bg-pine-100 text-pine'
                : 'border-brick/30 bg-brick-100 text-brick'"
        >
            <p class="font-medium" x-text="message"></p>
            <p x-show="detail" class="mt-1 text-xs opacity-80" x-text="detail"></p>
        </div>
    </template>

    <template x-if="busy">
        <p class="text-xs text-ink/40">{{ __('در حال بررسی موقعیت…') }}</p>
    </template>

    <div>
        <label class="{{ $label }}">{{ __('متن نظر') }}</label>
        <textarea class="{{ $input }}" name="body" rows="3" required></textarea>
    </div>

    {{-- دکمه تا وقتی نقطه مجاز نباشد غیرفعال است. سرور هم دوباره بررسی می‌کند،
         ولی غیرفعال‌کردن دکمه از تلاش بی‌نتیجه جلوگیری می‌کند. --}}
    <button
        type="submit"
        :disabled="!allowed"
        class="w-full rounded-lg bg-pine px-4 py-2.5 text-sm font-medium text-white transition hover:bg-pine-600 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"
    >
        {{ __('ثبت نظر') }}
    </button>
</form>
