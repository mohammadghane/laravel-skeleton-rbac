@php
    $actionStyle = [
        'created' => 'bg-pine-100 text-pine border-pine/30',
        'updated' => 'bg-gold-100 text-gold border-gold/30',
        'deleted' => 'bg-brick-100 text-brick border-brick/30',
        'restored' => 'bg-pine-100 text-pine border-pine/30',
    ];
    $actionLabel = [
        'created' => __('ایجاد شد'),
        'updated' => __('ویرایش شد'),
        'deleted' => __('حذف شد'),
        'restored' => __('بازیابی شد'),
    ];

    // Renders a single old/new value for display - arrays (e.g. role/permission list diffs)
    // are joined with a Persian comma, scalars pass through, empty values get a placeholder.
    $renderValue = function ($value) {
        if (is_array($value)) {
            return count($value) ? implode('، ', $value) : __('(خالی)');
        }
        return $value === null || $value === '' ? __('(خالی)') : $value;
    };
@endphp

<div class="divide-y divide-line">
    @forelse ($logs as $log)
        <div class="p-4 sm:p-5" x-data="{ open: false }">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $actionStyle[$log->action] ?? 'bg-black/5 text-ink/50 border-ink/10' }}">
                        {{ $actionLabel[$log->action] ?? $log->action }}
                    </span>
                    <span class="font-medium text-ink">{{ $log->user?->name }} {{ $log->user?->family }}</span>
                    <span class="text-ink/40">{{ $log->user ? '' : __('(سیستم)') }}</span>
                    <span class="text-ink/50">{{ __('روی') }}</span>
                    <span class="rounded-lg bg-surface px-2 py-0.5 font-medium text-ink/70">
                        {{ __($modelLabels[$log->auditable_type] ?? class_basename($log->auditable_type)) }}
                    </span>
                    <span class="text-ink/70">«{{ $log->label }}»</span>
                </div>
                <span class="whitespace-nowrap font-mono text-xs text-ink/40">{{ $log->created_at->format('Y/m/d H:i') }}</span>
            </div>

            @if (!empty($log->changes))
                <button type="button" @click="open = !open" class="mt-2 text-xs font-medium text-pine hover:underline">
                    <span x-show="!open">{{ __('نمایش جزئیات تغییرات') }} ({{ count($log->changes) }})</span>
                    <span x-show="open" style="display: none;">{{ __('پنهان کردن جزئیات') }}</span>
                </button>

                <div x-show="open" x-transition style="display: none;" class="mt-3 overflow-x-auto rounded-lg border border-line">
                    <table class="w-full text-start text-xs">
                        <thead>
                            <tr class="border-b border-line bg-surface text-ink/50">
                                <th class="px-3 py-2 font-medium">{{ __('فیلد') }}</th>
                                <th class="px-3 py-2 font-medium">{{ __('مقدار قبلی') }}</th>
                                <th class="px-3 py-2 font-medium">{{ __('مقدار جدید') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($log->changes as $field => $diff)
                                <tr>
                                    <td class="px-3 py-2 font-medium text-ink">{{ trans('validation.attributes.' . $field) === 'validation.attributes.' . $field ? $field : trans('validation.attributes.' . $field) }}</td>
                                    <td class="px-3 py-2 text-brick">{{ $renderValue($diff['old'] ?? null) }}</td>
                                    <td class="px-3 py-2 text-pine">{{ $renderValue($diff['new'] ?? null) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @empty
        <div class="p-10 text-center text-ink/40">{{ __('موردی یافت نشد') }}</div>
    @endforelse
</div>

<div class="border-t border-line px-5 py-3">
    {{ $logs->links() }}
</div>
