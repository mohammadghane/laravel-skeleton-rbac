<div class="overflow-x-auto">
    <table class="w-full text-start text-sm">
        <thead>
            <tr class="border-b border-line text-xs text-ink/50">
                <th class="px-5 py-3 font-medium w-10">
                    <input type="checkbox" class="select-all rounded border-line text-pine focus:ring-pine/20" @change="toggleAll($event.target.checked)">
                </th>
                <th class="px-5 py-3 font-medium">{{ __('عنوان معاونت') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('معاون (مسئول تایید)') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('تعداد واحدها') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('وضعیت') }}</th>
                <th class="px-5 py-3 font-medium"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-line">
            @forelse ($deputies as $deputy)
                <tr class="hover:bg-surface/60">
                    <td class="px-5 py-3.5">
                        <input type="checkbox" class="row-select rounded border-line text-pine focus:ring-pine/20" value="{{ $deputy->id }}" @change="toggle({{ $deputy->id }}, $event.target.checked)">
                    </td>
                    <td class="px-5 py-3.5 font-medium text-ink">{{ $deputy->title }}</td>
                    <td class="px-5 py-3.5 text-ink/60">
                        @if ($deputy->head)
                            {{ trim($deputy->head->name . ' ' . $deputy->head->family) }}
                        @else
                            {{-- بدون معاون، گردش تایید این معاونت قفل می‌ماند؛ پس صریح هشدار می‌دهیم. --}}
                            <span class="text-brick">{{ __('تعیین نشده') }}</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 font-mono text-xs text-ink/60">{{ $deputy->units->count() }}</td>
                    <td class="px-5 py-3.5">
                        @if ($deputy->is_active)
                            <span class="rounded-full border border-pine/30 bg-pine-100 px-2.5 py-1 text-xs font-medium text-pine">{{ __('فعال') }}</span>
                        @else
                            <span class="rounded-full border border-line bg-surface px-2.5 py-1 text-xs font-medium text-ink/50">{{ __('غیرفعال') }}</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5">
                        <div class="flex justify-end gap-2">
                            <a href="{{ route('admin.overtime.deputies.edit', $deputy) }}" class="open-modal rounded-lg border border-line px-3 py-1.5 text-xs font-medium text-ink/70 hover:bg-surface">{{ __('ویرایش') }}</a>
                            <button class="btn-remove rounded-lg border border-brick/20 bg-brick-100 px-3 py-1.5 text-xs font-medium text-brick hover:bg-brick/10" data-url="{{ route('admin.overtime.deputies.destroy', $deputy) }}">{{ __('حذف') }}</button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-5 py-10 text-center text-ink/40">{{ __('موردی یافت نشد') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
