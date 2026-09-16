{{--
    انتخابگر تک‌مقداری با جست‌وجو - جایگزین <select> برای فهرست‌های داینامیک.

    انگیزه: فهرست‌هایی مثل «مدیر واحد» چند صد گزینه دارند و پیمایش <select> بومی در
    آن‌ها عملاً غیرممکن است.

    ویژگی‌ها:
      - جست‌وجو با یکسان‌سازی فارسی (ي/ك عربی و ارقام فارسی) - همان قاعده‌ی
        App\Support\PersianText، تا تایپ با هر کیبوردی نتیجه بدهد.
      - یک <input type="hidden"> مقدار واقعی را می‌فرستد، پس سمت سرور هیچ تغییری لازم
        نیست و اعتبارسنجی‌های موجود دست‌نخورده کار می‌کنند.
      - پیمایش با کیبورد (بالا/پایین/Enter/Escape).

    پارامترها:
      $name        نام فیلد ارسالی
      $options     آرایه‌ای از ['value' => .., 'label' => .., 'hint' => ..(اختیاری)]
      $selected    مقدار انتخاب‌شده‌ی فعلی
      $placeholder متن گزینه‌ی خالی
      $required    اجباری بودن (روی input مخفی اعمال می‌شود)
      $inputClass  کلاس ظاهری، هم‌تراز بقیه‌ی فیلدهای همان فرم
--}}
@php
    $fieldId = 'ss-' . $name . '-' . uniqid();
    $normalized = collect($options)->map(fn ($o) => [
        'value' => (string) ($o['value'] ?? ''),
        'label' => (string) ($o['label'] ?? ''),
        'hint' => (string) ($o['hint'] ?? ''),
    ])->values();
@endphp

<div
    x-data="searchableSelect({
        options: {{ Js::from($normalized) }},
        selected: @js((string) ($selected ?? '')),
        placeholder: @js($placeholder ?? __('— انتخاب کنید —')),
    })"
    @keydown.escape.stop="close()"
    @click.outside="close()"
    class="relative"
>
    <input type="hidden" name="{{ $name }}" :value="selected" @if($required ?? false) required @endif>

    {{-- دکمه‌ی نمایش مقدار فعلی --}}
    <button
        type="button"
        @click="toggle()"
        class="{{ $inputClass ?? 'w-full rounded-lg border border-line px-3 py-2 text-sm focus:border-pine focus:outline-none focus:ring-2 focus:ring-pine/20' }} flex items-center justify-between gap-2 text-start"
        :class="open ? 'border-pine ring-2 ring-pine/20' : ''"
    >
        <span class="truncate" :class="selected ? 'text-ink' : 'text-ink/40'" x-text="currentLabel()"></span>
        <span class="shrink-0 text-ink/40" aria-hidden="true">▾</span>
    </button>

    <div x-show="open" x-transition x-cloak class="absolute z-30 mt-1 w-full rounded-lg border border-line bg-white shadow-card">
        <div class="border-b border-line p-2">
            <input
                x-ref="search"
                x-model="query"
                @keydown.down.prevent="move(1)"
                @keydown.up.prevent="move(-1)"
                @keydown.enter.prevent="pickHighlighted()"
                type="text"
                class="w-full rounded-md border border-line px-2 py-1.5 text-sm focus:border-pine focus:outline-none"
                placeholder="{{ __('جست‌وجو…') }}"
            >
        </div>

        <div class="max-h-56 overflow-y-auto py-1">
            {{-- گزینه‌ی خالی‌کردن انتخاب؛ برای فیلدهای اجباری نمایش داده نمی‌شود. --}}
            @unless ($required ?? false)
                <button
                    type="button"
                    @click="pick('')"
                    class="flex w-full items-center px-3 py-2 text-start text-sm text-ink/40 hover:bg-surface"
                >{{ $placeholder ?? __('— انتخاب کنید —') }}</button>
            @endunless

            <template x-for="(opt, i) in filtered()" :key="opt.value">
                <button
                    type="button"
                    @click="pick(opt.value)"
                    @mouseenter="highlight = i"
                    class="flex w-full items-center justify-between gap-2 px-3 py-2 text-start text-sm"
                    :class="[
                        opt.value === selected ? 'text-pine font-medium' : 'text-ink/70',
                        highlight === i ? 'bg-surface' : '',
                    ]"
                >
                    <span class="truncate">
                        <span x-text="opt.label"></span>
                        <span x-show="opt.hint" class="text-xs text-ink/40" x-text="' ' + opt.hint"></span>
                    </span>
                    <span x-show="opt.value === selected" class="shrink-0">✓</span>
                </button>
            </template>

            <p x-show="!filtered().length" class="px-3 py-3 text-center text-xs text-ink/40">{{ __('موردی یافت نشد') }}</p>
        </div>
    </div>
</div>
