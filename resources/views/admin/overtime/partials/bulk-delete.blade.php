{{--
    نوار حذف گروهی - مشترک بین همه‌ی فهرست‌ها.

    نحوه‌ی استفاده: این partial را داخل یک عنصر با x-data="bulkDelete('<آدرس>')" قرار
    دهید و در جدول، چک‌باکس‌ها را با کلاس `.row-select` (مقدار = id رکورد) و چک‌باکس
    سربرگ را با `.select-all` بگذارید.

    نکته: حذف گروهی همان قواعد حفاظتیِ حذف تکی را رعایت می‌کند (مثلاً واحدی که پرسنل
    دارد حذف نمی‌شود)؛ موارد رد شده با ذکر دلیل گزارش می‌شوند.

    پارامتر: $label - نام موجودیت برای نمایش در پیام تایید (مثلاً «واحد»)
--}}
<div x-show="selected.length > 0" class="mb-4 rounded-2xl border border-brick/30 bg-brick-100 p-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <span class="text-sm font-medium text-brick">
            <span x-text="selected.length"></span> {{ $label ?? __('مورد') }} {{ __('انتخاب شده') }}
        </span>

        <div class="flex items-center gap-2">
            <button type="button" @click="clearSelection()" class="text-xs text-brick underline">
                {{ __('لغو انتخاب') }}
            </button>

            <button
                type="button"
                @click="destroySelected()"
                :disabled="busy"
                class="rounded-lg bg-brick px-4 py-2 text-sm font-medium text-white transition hover:bg-brick/90 disabled:opacity-60"
            >
                <span x-show="!busy">{{ __('حذف انتخاب‌شده‌ها') }}</span>
                <span x-show="busy">{{ __('در حال حذف…') }}</span>
            </button>
        </div>
    </div>

    {{-- گزارش مواردی که به‌دلیل وابستگی حذف نشدند --}}
    <template x-if="blocked.length">
        <div class="mt-3 border-t border-brick/20 pt-3">
            <p class="mb-2 text-xs font-medium text-brick">{{ __('موارد حذف‌نشده:') }}</p>
            <ul class="max-h-40 space-y-1 overflow-y-auto">
                <template x-for="(item, i) in blocked" :key="i">
                    <li class="rounded-lg border border-brick/20 bg-white/60 px-3 py-1.5 text-xs text-brick" x-text="item"></li>
                </template>
            </ul>
        </div>
    </template>
</div>
