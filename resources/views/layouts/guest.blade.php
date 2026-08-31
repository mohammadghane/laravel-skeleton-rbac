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

    {{-- همان بستهٔ محلی صفحات پنل؛ هیچ فایلی از اینترنت گرفته نمی‌شود. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
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
