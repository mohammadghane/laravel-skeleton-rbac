<div class="overflow-x-auto">
    <table class="w-full text-start text-sm">
        <thead>
            <tr class="border-b border-line text-xs text-ink/50">
                <th class="px-5 py-3 font-medium">{{ __('عنوان آیتم') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('مسئول ثبت') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('نحوه‌ی مقداردهی') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('مقدار پیش‌فرض') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('پرسنل واجد شرایط') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('وضعیت') }}</th>
                <th class="px-5 py-3 font-medium"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-line">
            @forelse ($items as $item)
                <tr class="hover:bg-surface/60">
                    <td class="px-5 py-3.5 font-medium text-ink">{{ $item->title }}</td>

                    <td class="px-5 py-3.5">
                        {{-- آیتم مدیر واحد نیازمند تایید معاونت است، آیتم HR مستقیم اعمال می‌شود. --}}
                        @if ($item->submitter_role === \App\Models\SpecialItem::SUBMITTER_HR)
                            <span class="rounded-full border border-line bg-surface px-2.5 py-1 text-xs font-medium text-ink/60">{{ __('منابع انسانی') }}</span>
                        @else
                            <span class="rounded-full border border-gold/40 bg-gold-100 px-2.5 py-1 text-xs font-medium text-ink/80">{{ __('مدیر واحد') }}</span>
                        @endif
                    </td>

                    <td class="px-5 py-3.5 text-xs text-ink/60">
                        {{ $item->is_variable_per_personnel ? __('متغیر به ازای هر پرسنل') : __('ثابت برای همه') }}
                    </td>

                    <td class="px-5 py-3.5 font-mono text-xs text-ink/60">{{ $item->default_value }}</td>
                    <td class="px-5 py-3.5 font-mono text-xs text-ink/60">{{ $item->eligible_personnel_count }}</td>

                    <td class="px-5 py-3.5">
                        @if ($item->is_active)
                            <span class="rounded-full border border-pine/30 bg-pine-100 px-2.5 py-1 text-xs font-medium text-pine">{{ __('فعال') }}</span>
                        @else
                            <span class="rounded-full border border-line bg-surface px-2.5 py-1 text-xs font-medium text-ink/50">{{ __('غیرفعال') }}</span>
                        @endif
                    </td>

                    <td class="px-5 py-3.5">
                        <div class="flex justify-end gap-2">
                            <a href="{{ route('admin.overtime.special-items.edit', $item) }}" class="open-modal rounded-lg border border-line px-3 py-1.5 text-xs font-medium text-ink/70 hover:bg-surface">{{ __('ویرایش') }}</a>
                            <button class="btn-remove rounded-lg border border-brick/20 bg-brick-100 px-3 py-1.5 text-xs font-medium text-brick hover:bg-brick/10" data-url="{{ route('admin.overtime.special-items.destroy', $item) }}">{{ __('حذف') }}</button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-5 py-10 text-center text-ink/40">{{ __('موردی یافت نشد') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
