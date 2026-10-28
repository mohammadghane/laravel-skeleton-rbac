<div class="overflow-x-auto">
    <table class="w-full text-start text-sm">
        <thead>
            <tr class="border-b border-line text-xs text-ink/50">
                <th class="px-5 py-3 font-medium w-10">
                    <input type="checkbox" class="select-all rounded border-line text-pine focus:ring-pine/20" @change="toggleAll($event.target.checked)">
                </th>
                <th class="px-5 py-3 font-medium">{{ __('نوع قرارداد') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('تعداد پرسنل') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('سقف کلی قرارداد') }}<div class="font-normal text-ink/40">{{ __('حداکثر قابل پرداخت هر فرد') }}</div></th>
                <th class="px-5 py-3 font-medium">{{ __('روز فعال‌سازی') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('اعمال از') }}</th>
                <th class="px-5 py-3 font-medium"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-line">
            @forelse ($contractTypes as $type)
                @php $ceiling = $type->current_ceiling; @endphp
                <tr class="hover:bg-surface/60">
                    <td class="px-5 py-3.5">
                        <input
                            type="checkbox"
                            class="row-select rounded border-line text-pine focus:ring-pine/20"
                            value="{{ $type->id }}"
                            @change="toggle({{ $type->id }}, $event.target.checked)"
                        >
                    </td>

                    <td class="px-5 py-3.5 font-medium text-ink">{{ $type->title }}</td>
                    <td class="px-5 py-3.5 font-mono text-xs text-ink/60">{{ $type->personnel_count }}</td>

                    <td class="px-5 py-3.5">
                        {{-- سقف کلی قرارداد: مبنای capping خروجی مالی.
                             توجه: «سقف در اختیار مدیر واحد» از این‌جا به سمت‌های سازمانی
                             منتقل شده است، پس دیگر روی نوع قرارداد نمایش داده نمی‌شود. --}}
                        @if ($ceiling?->overall_ceiling !== null)
                            <span class="font-mono text-xs text-ink/70">{{ $ceiling->overall_ceiling }}</span>
                        @else
                            <span class="text-xs text-ink/30">{{ __('بدون سقف') }}</span>
                        @endif
                    </td>

                    <td class="px-5 py-3.5">
                        @if ($ceiling)
                            <span class="font-mono text-xs text-ink/60">{{ __('روز') }} {{ $ceiling->activation_day }}</span>
                            @if ($ceiling->isRegistrationWindowOpen($year, $month))
                                <span class="ms-1 rounded-full border border-pine/30 bg-pine-100 px-2 py-0.5 text-[11px] font-medium text-pine">{{ __('باز') }}</span>
                            @else
                                <span class="ms-1 rounded-full border border-line bg-surface px-2 py-0.5 text-[11px] font-medium text-ink/50">{{ __('بسته') }}</span>
                            @endif
                        @else
                            <span class="text-ink/30">—</span>
                        @endif
                    </td>

                    <td class="px-5 py-3.5">
                        {{-- تفکیک «برای همین ماه ثبت شده» از «از ماه قبل ادامه دارد» - بدون این،
                             کاربر تصور می‌کند برای هر ماه باید دوباره ثبت کند. --}}
                        @if (!$ceiling)
                            <span class="text-ink/30">—</span>
                        @elseif ($type->ceiling_is_inherited)
                            <span class="font-mono text-[11px] text-ink/50" title="{{ __('این مقدار از نسخه‌ی قبلی ادامه یافته است') }}">
                                {{ \App\Support\Jalali::periodLabel($ceiling->jalali_year, $ceiling->jalali_month) }}
                                <span class="text-ink/40">{{ __('(ادامه)') }}</span>
                            </span>
                        @else
                            <span class="rounded-full border border-pine/30 bg-pine-100 px-2 py-0.5 font-mono text-[11px] font-medium text-pine">
                                {{ \App\Support\Jalali::periodLabel($ceiling->jalali_year, $ceiling->jalali_month) }}
                            </span>
                        @endif
                    </td>

                    <td class="px-5 py-3.5">
                        <div class="flex justify-end gap-2">
                            <a href="{{ route('admin.overtime.contract-types.ceilings.index', $type) }}" class="rounded-lg border border-line px-3 py-1.5 text-xs font-medium text-ink/70 hover:bg-surface">{{ __('سقف‌ها') }}</a>
                            <a href="{{ route('admin.overtime.contract-types.edit', $type) }}" class="open-modal rounded-lg border border-line px-3 py-1.5 text-xs font-medium text-ink/70 hover:bg-surface">{{ __('ویرایش') }}</a>
                            <button class="btn-remove rounded-lg border border-brick/20 bg-brick-100 px-3 py-1.5 text-xs font-medium text-brick hover:bg-brick/10" data-url="{{ route('admin.overtime.contract-types.destroy', $type) }}">{{ __('حذف') }}</button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-5 py-10 text-center text-ink/40">{{ __('موردی یافت نشد') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
