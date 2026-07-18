@php
    // 'fa' (and other RTL languages, if you add them) render dir="rtl"; everything else "ltr".
    // Add to this list if you add more RTL locales later.
    $dir = in_array(app()->getLocale(), config('locales.rtl')) ? 'rtl' : 'ltr';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $dir }}" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('پنل مدیریت'))</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    {{-- Tailwind Play CDN for zero-build drop-in. For production, compile via Vite instead
         (npm i -D tailwindcss, wire tailwind.config.js with the tokens below) and drop this
         script tag + inline config. --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        // Vazirmatn covers both Persian and Latin glyphs, so we don't need to
                        // swap font-family when switching locale - only Inter as an LTR-tuned
                        // fallback for locales where Vazirmatn's Latin metrics feel off.
                        sans: ['Vazirmatn', 'Inter', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    },
                    colors: {
                        ink: { DEFAULT: '#1C2333', soft: '#2A3244' },
                        surface: '#F5F6F3',
                        pine: { DEFAULT: '#3E6259', 600: '#345049', 100: '#E4ECE9' },
                        gold: { DEFAULT: '#C9A227', 100: '#FBF3D9' },
                        brick: { DEFAULT: '#B4432F', 100: '#F7E4E0' },
                        line: '#E3E5E0',
                    },
                    boxShadow: {
                        card: '0 1px 2px 0 rgb(28 35 51 / 0.04), 0 1px 3px 0 rgb(28 35 51 / 0.06)',
                    },
                },
            },
        };
    </script>

    {{-- Alpine.js — declarative state for the sidebar drawer, profile menu, and toasts (no build step). --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>

    @stack('styles')
</head>
<body class="h-full bg-surface font-sans text-ink antialiased" x-data="{ sidebarOpen: false }">

    <div class="flex h-full">

        {{-- Mobile overlay --}}
        <div
            x-show="sidebarOpen"
            x-transition.opacity
            @click="sidebarOpen = false"
            class="fixed inset-0 z-30 bg-ink/40 lg:hidden"
            style="display: none;"
        ></div>

        {{-- Sidebar. `x-show` toggles a plain inline display:none/flex - the same mechanism
             already used successfully by the overlay backdrop and profile dropdown below,
             instead of a dynamic transform class the Tailwind Play CDN wasn't reliably
             generating. `lg:!flex` forces it visible at desktop regardless of sidebarOpen,
             since `!important` is the only thing that can override an inline style. --}}
        <aside
            x-show="sidebarOpen"
            class="fixed inset-y-0 end-0 z-40 flex w-64 shrink-0 flex-col bg-ink lg:static lg:!flex"
            style="display: none;"
        >
            {{-- Logo + hierarchy rail: three small ticks colour-coded to the admin/user/guest
                 tiers this ACL system is built around — a nod to the org hierarchy the whole
                 module exists to model, not a decorative flourish. --}}
            <div class="flex items-center gap-3 border-b border-white/10 px-5 py-5">
                <div class="flex flex-col gap-1">
                    <span class="h-1.5 w-6 rounded-full bg-gold"></span>
                    <span class="h-1.5 w-6 rounded-full bg-pine"></span>
                    <span class="h-1.5 w-6 rounded-full bg-white/25"></span>
                </div>
                <span class="text-base font-bold text-white">{{ __('پنل مدیریت') }}</span>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                <p class="px-3 pb-2 text-xs font-medium text-white/40">{{ __('دسترسی سریع') }}</p>

                <a
                    href="{{ route('admin.profile') }}"
                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition
                        {{ request()->routeIs('admin.profile') ? 'bg-white/10 text-white' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                >
                    <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                    {{ __('پروفایل من') }}
                </a>

                @permission('admin/users')
                    <a
                        href="{{ route('admin.users.index') }}"
                        class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition
                            {{ request()->routeIs('admin.users.*') ? 'bg-white/10 text-white' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                        {{ __('کاربران') }}
                    </a>
                @endpermission

                @permission('admin/roles')
                    <a
                        href="{{ route('admin.roles.index') }}"
                        class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition
                            {{ request()->routeIs('admin.roles.*') ? 'bg-white/10 text-white' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                        {{ __('نقش‌های سازمانی') }}
                    </a>
                @endpermission

                @permission('admin/audit-logs')
                    <a
                        href="{{ route('admin.audit-logs.index') }}"
                        class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition
                            {{ request()->routeIs('admin.audit-logs.*') ? 'bg-white/10 text-white' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                        {{ __('گزارش تغییرات') }}
                    </a>
                @endpermission

                <a
                    href="{{ route('admin.settings.edit') }}"
                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition
                        {{ request()->routeIs('admin.settings.*') ? 'bg-white/10 text-white' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                >
                    <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                    {{ __('تنظیمات') }}
                </a>

                @yield('sidebar-extra')
            </nav>

            <div class="border-t border-white/10 px-5 py-4">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-white/60 transition hover:bg-white/5 hover:text-white">
                        <span class="h-1.5 w-1.5 rounded-full bg-brick"></span>
                        {{ __('خروج از حساب') }}
                    </button>
                </form>
            </div>
        </aside>

        {{-- Main column --}}
        <div class="flex min-w-0 flex-1 flex-col">

            {{-- Topbar --}}
            <header class="sticky top-0 z-20 flex h-16 shrink-0 items-center justify-between border-b border-line bg-white/90 px-4 backdrop-blur sm:px-6">
                <div class="flex items-center gap-3">
                    <button
                        @click="sidebarOpen = true"
                        class="rounded-lg p-2 text-ink/60 hover:bg-surface lg:hidden"
                        aria-label="{{ __('باز کردن منو') }}"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <h1 class="text-base font-semibold text-ink sm:text-lg">@yield('title', __('داشبورد'))</h1>
                </div>

                <div class="flex items-center gap-2">
                    @include('partials.locale-switcher')

                    @auth
                        @php
                            $roleNames = auth()->user()->roles->pluck('name')->all();
                            $tier = in_array('admin', $roleNames) ? 'admin' : (in_array('user', $roleNames) ? 'user' : 'guest');
                            $tierStyle = [
                                'admin' => 'bg-gold-100 text-gold border-gold/30',
                                'user' => 'bg-pine-100 text-pine border-pine/30',
                                'guest' => 'bg-black/5 text-ink/50 border-ink/10',
                            ][$tier];
                            $tierLabel = ['admin' => __('مدیر'), 'user' => __('کاربر'), 'guest' => __('مهمان')][$tier];
                        @endphp
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" @click.outside="open = false" class="flex items-center gap-3 rounded-lg px-2 py-1.5 hover:bg-surface">
                                <span class="hidden text-end sm:block">
                                    <span class="block text-sm font-medium text-ink">{{ auth()->user()->name }} {{ auth()->user()->family }}</span>
                                    <span class="block text-xs text-ink/50">{{ auth()->user()->username }}</span>
                                </span>
                                <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-medium {{ $tierStyle }}">
                                    {{ $tierLabel }}
                                </span>
                            </button>

                            <div
                                x-show="open"
                                x-transition
                                class="absolute end-0 mt-2 w-44 rounded-xl border border-line bg-white py-1 shadow-card"
                                style="display: none;"
                            >
                                <a href="{{ route('admin.profile') }}" class="block px-4 py-2 text-sm text-ink/70 hover:bg-surface">{{ __('پروفایل من') }}</a>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="block w-full px-4 py-2 text-start text-sm text-brick hover:bg-brick-100">{{ __('خروج') }}</button>
                                </form>
                            </div>
                        </div>
                    @endauth
                </div>
            </header>

            {{-- Page content --}}
            <main class="flex-1 overflow-y-auto p-4 sm:p-6">
                <div class="mx-auto max-w-6xl">
                    @yield('content')
                </div>
            </main>
        </div>
    </div>

    {{-- Shared modal target, populated by acl-admin.js for the roles/users add-edit-permissions forms.
         Closes only via the explicit ✕ button or Escape - clicking the backdrop does nothing,
         so an accidental outside click can't discard unsaved form input. --}}
    <div
        id="admin-modal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-ink/50 p-4"
    >
        <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-card">
            <div class="mb-4 flex items-center justify-between">
                <span class="text-sm font-medium text-ink/40">&nbsp;</span>
                <button type="button" data-modal-close class="rounded-lg p-1 text-ink/40 hover:bg-surface hover:text-ink">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="modal-body"></div>
        </div>
    </div>

    @include('partials.toast')
    @include('partials.js-i18n')

    <script src="{{ asset('js/acl-admin.js') }}"></script>
    @stack('scripts')
</body>
</html>
