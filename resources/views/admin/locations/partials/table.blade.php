<div class="overflow-x-auto">
    <table class="w-full text-start text-sm">
        <thead>
            <tr class="border-b border-line text-xs text-ink/50">
                <th class="px-5 py-3 font-medium w-10">
                    <input type="checkbox" class="select-all rounded border-line text-pine focus:ring-pine/20" @change="toggleAll($event.target.checked)">
                </th>
                <th class="px-5 py-3 font-medium">{{ __('عنوان') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('مختصات') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('توضیحات') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('ثبت‌کننده') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('وضعیت') }}</th>
                <th class="px-5 py-3 font-medium"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-line">
            @forelse ($locations as $location)
                <tr class="hover:bg-surface/60">
                    <td class="px-5 py-3.5">
                        <input type="checkbox" class="row-select rounded border-line text-pine focus:ring-pine/20" value="{{ $location->id }}" @change="toggle({{ $location->id }}, $event.target.checked)">
                    </td>
                    <td class="px-5 py-3.5 font-medium text-ink">{{ $location->title }}</td>
                    <td class="px-5 py-3.5 font-mono text-xs text-ink/60" dir="ltr">
                        {{ $location->latitude }}, {{ $location->longitude }}
                    </td>
                    <td class="px-5 py-3.5 text-xs text-ink/50">{{ \Illuminate\Support\Str::limit($location->description, 60) ?: '—' }}</td>
                    <td class="px-5 py-3.5 text-xs text-ink/50">
                        {{ $location->creator ? trim($location->creator->name . ' ' . $location->creator->family) : '—' }}
                    </td>
                    <td class="px-5 py-3.5">
                        @if ($location->is_active)
                            <span class="rounded-full border border-pine/30 bg-pine-100 px-2.5 py-1 text-xs text-pine">{{ __('فعال') }}</span>
                        @else
                            <span class="rounded-full border border-line bg-surface px-2.5 py-1 text-xs text-ink/40">{{ __('غیرفعال') }}</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5">
                        <div class="flex justify-end gap-2">
                            <a href="{{ route('admin.locations.edit', $location) }}" class="open-modal rounded-lg border border-line px-3 py-1.5 text-xs font-medium text-ink/70 hover:bg-surface">{{ __('ویرایش') }}</a>
                            <button class="btn-remove rounded-lg border border-brick/20 bg-brick-100 px-3 py-1.5 text-xs font-medium text-brick hover:bg-brick/10" data-url="{{ route('admin.locations.destroy', $location) }}">{{ __('حذف') }}</button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-5 py-10 text-center text-ink/40">{{ __('هنوز موقعیتی ثبت نشده است.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
