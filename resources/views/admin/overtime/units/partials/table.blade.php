<div class="overflow-x-auto">
    <table class="w-full text-start text-sm">
        <thead>
            <tr class="border-b border-line text-xs text-ink/50">
                <th class="px-5 py-3 font-medium">{{ __('عنوان واحد') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('معاونت') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('مدیر واحد (مسئول ثبت)') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('وضعیت') }}</th>
                <th class="px-5 py-3 font-medium"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-line">
            @forelse ($units as $unit)
                <tr class="hover:bg-surface/60">
                    <td class="px-5 py-3.5 font-medium text-ink">{{ $unit->title }}</td>
                    <td class="px-5 py-3.5 text-ink/60">{{ $unit->deputy?->title ?? '—' }}</td>
                    <td class="px-5 py-3.5 text-ink/60">
                        @if ($unit->manager)
                            {{ trim($unit->manager->name . ' ' . $unit->manager->family) }}
                        @else
                            {{-- بدون مدیر، هیچ‌کس نمی‌تواند برای پرسنل این واحد اضافه‌کار ثبت کند. --}}
                            <span class="text-brick">{{ __('تعیین نشده') }}</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5">
                        @if ($unit->is_active)
                            <span class="rounded-full border border-pine/30 bg-pine-100 px-2.5 py-1 text-xs font-medium text-pine">{{ __('فعال') }}</span>
                        @else
                            <span class="rounded-full border border-line bg-surface px-2.5 py-1 text-xs font-medium text-ink/50">{{ __('غیرفعال') }}</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5">
                        <div class="flex justify-end gap-2">
                            <a href="{{ route('admin.overtime.units.edit', $unit) }}" class="open-modal rounded-lg border border-line px-3 py-1.5 text-xs font-medium text-ink/70 hover:bg-surface">{{ __('ویرایش') }}</a>
                            <button class="btn-remove rounded-lg border border-brick/20 bg-brick-100 px-3 py-1.5 text-xs font-medium text-brick hover:bg-brick/10" data-url="{{ route('admin.overtime.units.destroy', $unit) }}">{{ __('حذف') }}</button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-5 py-10 text-center text-ink/40">{{ __('موردی یافت نشد') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
