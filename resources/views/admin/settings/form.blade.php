<h3 class="mb-4 text-base font-semibold text-ink">{{ __('تنظیمات') }}</h3>

<div x-data="{ tab: @js(array_key_first($groups)) }">
    <div class="mb-4 flex gap-1 overflow-x-auto border-b border-line">
        @foreach ($groups as $groupKey => $group)
            <button
                type="button"
                @click="tab = '{{ $groupKey }}'"
                :class="tab === '{{ $groupKey }}' ? 'border-pine text-pine' : 'border-transparent text-ink/50 hover:text-ink'"
                class="shrink-0 border-b-2 px-3 py-2 text-sm font-medium transition"
            >
                {{ __($group['title']) }}
            </button>
        @endforeach
    </div>

    <form id="settings-form" method="POST" action="{{ $action }}" data-callback="reloadSettingsGrid">
        @csrf

        @foreach ($groups as $groupKey => $group)
            <div x-show="tab === '{{ $groupKey }}'" class="space-y-4">
                @foreach ($group['items'] as $itemKey => $item)
                    @php $fieldName = "{$groupKey}:{$itemKey}"; @endphp

                    @if (($item['type'] ?? 'text') === 'textarea')
                        <div>
                            <label for="{{ $fieldName }}" class="mb-1.5 block text-sm font-medium text-ink/70">{{ __($item['title']) }}</label>
                            <textarea
                                id="{{ $fieldName }}" name="{{ $fieldName }}"
                                dir="{{ $item['dir'] ?? 'auto' }}"
                                class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:border-pine focus:outline-none focus:ring-2 focus:ring-pine/20"
                                rows="3"
                            >{{ $item['default_value'] ?? '' }}</textarea>
                            @if (isset($item['desk']))
                                <p class="mt-1 text-xs text-ink/40">{{ __($item['desk']) }}</p>
                            @endif
                        </div>

                    @elseif (($item['type'] ?? 'text') === 'text')
                        <div>
                            <label for="{{ $fieldName }}" class="mb-1.5 block text-sm font-medium text-ink/70">{{ __($item['title']) }}</label>
                            <input
                                type="text" id="{{ $fieldName }}" name="{{ $fieldName }}"
                                value="{{ $item['default_value'] ?? '' }}"
                                class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:border-pine focus:outline-none focus:ring-2 focus:ring-pine/20"
                            >
                            @if (isset($item['desk']))
                                <p class="mt-1 text-xs text-ink/40">{{ __($item['desk']) }}</p>
                            @endif
                        </div>

                    @elseif ($item['type'] === 'checkbox')
                        <div>
                            <input type="hidden" name="{{ $fieldName }}" value="0">
                            <label class="flex items-center gap-2 text-sm text-ink/80">
                                <input
                                    type="checkbox" name="{{ $fieldName }}" value="1"
                                    class="h-4 w-4 rounded border-line text-pine focus:ring-pine/30"
                                    @checked(!empty($item['default_value']))
                                >
                                {{ __($item['title']) }}
                            </label>
                            @if (isset($item['desk']))
                                <p class="mt-1 text-xs text-ink/40">{{ __($item['desk']) }}</p>
                            @endif
                        </div>

                    @elseif ($item['type'] === 'select')
                        <div>
                            <label for="{{ $fieldName }}" class="mb-1.5 block text-sm font-medium text-ink/70">{{ __($item['title']) }}</label>
                            <select id="{{ $fieldName }}" name="{{ $fieldName }}" class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:border-pine focus:outline-none focus:ring-2 focus:ring-pine/20">
                                <option value="">{{ __('انتخاب نمایید...') }}</option>
                                @foreach ($item['options'] ?? [] as $option)
                                    <option value="{{ $option['value'] }}" @selected(($item['default_value'] ?? null) == $option['value'])>
                                        {{ __($option['text']) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                    @elseif ($item['type'] === 'from_to')
                        @php $range = is_array($item['default_value'] ?? null) ? $item['default_value'] : ['from' => '', 'to' => '']; @endphp
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-ink/70">{{ __($item['title']) }}</label>
                            <div class="grid grid-cols-2 gap-3">
                                <input
                                    type="text" name="{{ $fieldName }}[from]" value="{{ $range['from'] ?? '' }}" placeholder="{{ __('از') }}"
                                    class="w-full rounded-lg border border-line px-3 py-2 text-sm font-mono focus:border-pine focus:outline-none focus:ring-2 focus:ring-pine/20"
                                >
                                <input
                                    type="text" name="{{ $fieldName }}[to]" value="{{ $range['to'] ?? '' }}" placeholder="{{ __('تا') }}"
                                    class="w-full rounded-lg border border-line px-3 py-2 text-sm font-mono focus:border-pine focus:outline-none focus:ring-2 focus:ring-pine/20"
                                >
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        @endforeach

        <button type="submit" class="mt-5 w-full rounded-lg bg-pine px-4 py-2.5 text-sm font-medium text-white hover:bg-pine-600 sm:w-auto">{{ __('ذخیره تنظیمات') }}</button>
    </form>
</div>
