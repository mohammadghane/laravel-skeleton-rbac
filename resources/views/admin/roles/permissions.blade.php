<h3 class="mb-4 text-base font-semibold text-ink">{{ __('دسترسی‌های نقش') }}: {{ $role->name }}</h3>

<div x-data="{ tab: @js(array_key_first($modules)) }">
    <div class="mb-4 flex gap-1 overflow-x-auto border-b border-line">
        @foreach ($modules as $moduleKey => $module)
            <button
                type="button"
                @click="tab = '{{ $moduleKey }}'"
                :class="tab === '{{ $moduleKey }}' ? 'border-pine text-pine' : 'border-transparent text-ink/50 hover:text-ink'"
                class="shrink-0 border-b-2 px-3 py-2 text-sm font-medium transition"
            >
                {{ __($module['title']) }}
            </button>
        @endforeach
    </div>

    <form id="perms-form" method="POST" action="{{ route('admin.roles.permissions.store', $role) }}">
        @csrf

        @foreach ($modules as $moduleKey => $module)
            <div x-show="tab === '{{ $moduleKey }}'" class="space-y-4">
                @foreach ($module['perms'] as $group)
                    @if (!empty($group['items']))
                        <div class="rounded-lg bg-pine-100 px-3 py-2 text-xs font-medium text-pine">{{ __($group['title']) }}</div>
                    @endif
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        @foreach ($group['items'] as $key => $item)
                            <label class="flex items-center gap-2 rounded-lg border border-line px-3 py-2 text-sm text-ink/80 hover:bg-surface">
                                <input
                                    type="checkbox" name="{{ $key }}" value="1"
                                    class="h-4 w-4 rounded border-line text-pine focus:ring-pine/30"
                                    @checked(in_array($key, $current))
                                >
                                {{ __($item['title']) }}
                            </label>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endforeach

        <button type="submit" class="mt-5 w-full rounded-lg bg-pine px-4 py-2.5 text-sm font-medium text-white hover:bg-pine-600 sm:w-auto">{{ __('ذخیره دسترسی‌ها') }}</button>
    </form>
</div>
