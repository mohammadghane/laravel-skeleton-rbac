{{--
    صفحه‌ی پیش‌فرض لاراول در این پروژه استفاده نمی‌شود: روت `/` به صفحه‌ی ورود
    هدایت می‌شود (routes/web.php).

    نسخه‌ی اصلی این فایل به fonts.bunny.net و laravel.com وصل می‌شد؛ چون پروژه
    نباید هیچ فایلی از اینترنت بگیرد، با این نسخه‌ی خودبسنده جایگزین شد.
--}}
@extends('layouts.guest')

@section('title', __('سامانه‌ی مدیریت اضافه‌کار'))

@section('content')
    <div class="text-center">
        <h1 class="mb-2 text-xl font-semibold text-ink">{{ __('سامانه‌ی مدیریت اضافه‌کار پرسنل') }}</h1>
        <p class="mb-6 text-sm text-ink/60">{{ __('برای ادامه وارد شوید.') }}</p>

        <a href="{{ route('login') }}" class="inline-block rounded-lg bg-pine px-5 py-2.5 text-sm font-medium text-white hover:bg-pine-600">
            {{ __('ورود به سامانه') }}
        </a>
    </div>
@endsection
