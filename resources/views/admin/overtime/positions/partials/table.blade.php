<div class="overflow-x-auto">
    <table class="w-full text-start text-sm">
        <thead>
            <tr class="border-b border-line text-xs text-ink/50">
                <th class="px-5 py-3 font-medium w-10">
                    <input type="checkbox" class="select-all rounded border-line text-pine focus:ring-pine/20" @change="toggleAll($event.target.checked)">
                </th>
                <th class="px-5 py-3 font-medium">{{ __('عنوان سمت') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('تعداد پرسنل') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('سقف عادی هر نفر') }}<div class="font-normal text-ink/40">{{ __('در اختیار مدیر واحد') }}</div></th>
                <th class="px-5 py-3 font-medium">{{ __('سقف مازاد هر نفر') }}<div class="font-normal text-ink/40">{{ __('در اختیار معاونت') }}</div></th>
                <th class="px-5 py-3 font-medium">{{ __('سقف کل عادی') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('اعمال از') }}</th>
                <th class="px-5 py-3 font-medium"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-line">
            @forelse ($positions as $position)
                @php $ceiling = $position->current_ceiling; @endphp
                <tr class="hover:bg-surface/60">
                    <td class="px-5 py-3.5">
                        <input
                            type="checkbox"
                            class="row-select rounded border-line text-pine focus:ring-pine/20"
                            value="{{ $position->id }}"
                            @change="toggle({{ $position->id }}, $event.target.checked)"
                        >
                    </td>

                    <td class="px-5 py-3.5 font-medium text-ink">{{ $position->title }}</td>
                    <td class="px-5 py-3.5 font-mono text-xs text-ink/60">{{ $position->personnel_count }}</td>

                    <td class="px-5 py-3.5">
                        {{-- سقف «عادی» در اختیار مدیر واحد --}}
                        @if ($ceiling?->manager_ceiling_per_person !== null)
                            <span class="font-mono text-xs text-ink/70">{{ $ceiling->manager_ceiling_per_person }}</span>
                        @else
                            <span class="text-xs text-gold">{{ __('تعریف نشده') }}</span>
                        @endif
                    </td>

                    <td class="px-5 py-3.5">
                        {{-- سقف «مازاد» در اختیار معاونت --}}
                        @if ($ceiling)
                            <span class="font-mono text-xs text-ink/70">{{ $ceiling->ceiling_per_person }}</span>
                        @else
                            <span class="text-xs text-gold">{{ __('تعریف نشده') }}</span>
                        @endif
                    </td>

                    <td class="px-5 py-3.5">
                        {{-- سقف کل گروه = تعداد پرسنل × سقف عادی هر نفر --}}
                        @if ($ceiling?->manager_ceiling_per_person !== null)
                            <span class="font-mono text-xs text-ink/70">{{ bcmul((string) $position->personnel_count, (string) $ceiling->manager_ceiling_per_person, 2) }}</span>
                        @else
                            <span class="text-ink/30">—</span>
                        @endif
                    </td>

                    <td class="px-5 py-3.5">
                        @if (!$ceiling)
                            <span class="text-ink/30">—</span>
                        @elseif ($position->ceiling_is_inherited)
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
                            <a href="{{ route('admin.overtime.positions.ceilings.index', $position) }}" class="rounded-lg border border-line px-3 py-1.5 text-xs font-medium text-ink/70 hover:bg-surface">{{ __('سقف‌ها') }}</a>
                            <a href="{{ route('admin.overtime.positions.edit', $position) }}" class="open-modal rounded-lg border border-line px-3 py-1.5 text-xs font-medium text-ink/70 hover:bg-surface">{{ __('ویرایش') }}</a>
                            <button class="btn-remove rounded-lg border border-brick/20 bg-brick-100 px-3 py-1.5 text-xs font-medium text-brick hover:bg-brick/10" data-url="{{ route('admin.overtime.positions.destroy', $position) }}">{{ __('حذف') }}</button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="px-5 py-10 text-center text-ink/40">{{ __('موردی یافت نشد') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
