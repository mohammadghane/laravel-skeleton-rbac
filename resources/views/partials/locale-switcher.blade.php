@php
    $locales = config('locales.available');
@endphp
<div class="relative" x-data="{ open: false }">
    <button
        @click="open = !open" @click.outside="open = false"
        class="flex items-center gap-1.5 rounded-lg border border-line px-2.5 py-1.5 text-xs font-medium text-ink/70 hover:bg-surface"
    >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12h18M12 3c2.5 2.8 4 6.4 4 9s-1.5 6.2-4 9c-2.5-2.8-4-6.4-4-9s1.5-6.2 4-9z" />
        </svg>
        {{ $locales[app()->getLocale()] ?? app()->getLocale() }}
    </button>

    <div
        x-show="open" x-transition
        class="absolute end-0 z-10 mt-2 w-32 rounded-xl border border-line bg-white py-1 shadow-card"
        style="display: none;"
    >
        @foreach ($locales as $code => $label)
            <a
                href="{{ route('locale.switch', $code) }}"
                class="flex items-center justify-between px-4 py-2 text-sm hover:bg-surface {{ app()->getLocale() === $code ? 'font-semibold text-pine' : 'text-ink/70' }}"
            >
                {{ $label }}
            </a>
        @endforeach
    </div>
</div>
