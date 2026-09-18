<div class="overflow-x-auto">
    <table class="w-full text-start text-sm">
        <thead>
            <tr class="border-b border-line text-xs text-ink/50">
                <th class="px-5 py-3 font-medium w-10">
                    <input type="checkbox" class="select-all rounded border-line text-pine focus:ring-pine/20" @change="toggleAll($event.target.checked)">
                </th>
                <th class="px-5 py-3 font-medium">{{ __('کاربر') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('نظر') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('موقعیت') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('فاصله') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('مختصات') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('تاریخ') }}</th>
                <th class="px-5 py-3 font-medium"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-line">
            @forelse ($comments as $comment)
                <tr class="hover:bg-surface/60">
                    <td class="px-5 py-3.5">
                        <input type="checkbox" class="row-select rounded border-line text-pine focus:ring-pine/20" value="{{ $comment->id }}" @change="toggle({{ $comment->id }}, $event.target.checked)">
                    </td>
                    <td class="px-5 py-3.5 text-ink">
                        {{ $comment->user ? trim($comment->user->name . ' ' . $comment->user->family) : '—' }}
                    </td>
                    <td class="px-5 py-3.5 text-xs text-ink/70">{{ \Illuminate\Support\Str::limit($comment->body, 70) }}</td>
                    <td class="px-5 py-3.5 text-xs text-ink/60">{{ $comment->location?->title ?? '—' }}</td>
                    <td class="px-5 py-3.5 font-mono text-xs text-ink/50">
                        {{ $comment->distance_meters !== null ? number_format($comment->distance_meters) . ' ' . __('متر') : '—' }}
                    </td>
                    <td class="px-5 py-3.5 font-mono text-xs text-ink/50" dir="ltr">
                        {{ $comment->latitude }}, {{ $comment->longitude }}
                    </td>
                    <td class="px-5 py-3.5 font-mono text-xs text-ink/50">{{ $comment->created_at?->format('Y/m/d H:i') }}</td>
                    <td class="px-5 py-3.5">
                        <div class="flex justify-end">
                            <button class="btn-remove rounded-lg border border-brick/20 bg-brick-100 px-3 py-1.5 text-xs font-medium text-brick hover:bg-brick/10" data-url="{{ route('admin.location-comments.destroy', $comment) }}">{{ __('حذف') }}</button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="px-5 py-10 text-center text-ink/40">{{ __('هنوز نظری ثبت نشده است.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
