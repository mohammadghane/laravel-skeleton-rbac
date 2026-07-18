@php
    $dir = in_array(app()->getLocale(), config('locales.rtl')) ? 'rtl' : 'ltr';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $dir }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('ورود'))</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Vazirmatn', 'Inter', 'sans-serif'] },
                    colors: {
                        ink: { DEFAULT: '#1C2333', soft: '#2A3244' },
                        surface: '#F5F6F3',
                        pine: { DEFAULT: '#3E6259', 600: '#345049', 100: '#E4ECE9' },
                        gold: { DEFAULT: '#C9A227' },
                        brick: { DEFAULT: '#B4432F', 100: '#F7E4E0' },
                        line: '#E3E5E0',
                    },
                    boxShadow: { card: '0 1px 2px 0 rgb(28 35 51 / 0.04), 0 1px 3px 0 rgb(28 35 51 / 0.06)' },
                },
            },
        };
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
</head>
<body class="flex h-full items-center justify-center bg-surface font-sans text-ink antialiased">
    <div class="absolute top-4 end-4">
        @include('partials.locale-switcher')
    </div>

    <div class="w-full max-w-sm px-4">
        <div class="mb-6 flex justify-center gap-1">
            <span class="h-1.5 w-6 rounded-full bg-gold"></span>
            <span class="h-1.5 w-6 rounded-full bg-pine"></span>
            <span class="h-1.5 w-6 rounded-full bg-ink/20"></span>
        </div>
        <div class="rounded-2xl border border-line bg-white p-6 shadow-card sm:p-8">
            @yield('content')
        </div>
    </div>

    @include('partials.toast')
    @include('partials.js-i18n')
    <script src="{{ asset('js/acl-admin.js') }}"></script>
</body>
</html>
