@extends('layouts.guest')

@section('title', __('ورود به سیستم'))

@section('content')
    <h4 class="mb-6 text-center text-lg font-bold text-ink">{{ __('ورود به سیستم') }}</h4>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-pine/20 bg-pine-100 px-4 py-2.5 text-sm text-pine">{{ session('status') }}</div>
    @endif

    @error('login')
        <div class="mb-4 rounded-lg border border-brick/20 bg-brick-100 px-4 py-2.5 text-sm text-brick">{{ $message }}</div>
    @enderror

    <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
        @csrf

        <div>
            <label for="login" class="mb-1.5 block text-sm font-medium text-ink/70">{{ __('نام کاربری یا ایمیل') }}</label>
            <input
                type="text" name="login" id="login" required autofocus autocomplete="username"
                value="{{ old('login') }}"
                class="w-full rounded-lg border px-3 py-2.5 text-sm focus:outline-none focus:ring-2
                    {{ $errors->has('login') ? 'border-brick focus:ring-brick/20' : 'border-line focus:border-pine focus:ring-pine/20' }}"
            >
        </div>

        <div>
            <label for="password" class="mb-1.5 block text-sm font-medium text-ink/70">{{ __('کلمه عبور') }}</label>
            <input
                type="password" name="password" id="password" required autocomplete="current-password"
                class="w-full rounded-lg border px-3 py-2.5 text-sm focus:outline-none focus:ring-2
                    {{ $errors->has('password') ? 'border-brick focus:ring-brick/20' : 'border-line focus:border-pine focus:ring-pine/20' }}"
            >
            @error('password')
                <p class="mt-1 text-xs text-brick">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-ink/60">
            <input type="checkbox" name="remember" class="h-4 w-4 rounded border-line text-pine focus:ring-pine/30">
            {{ __('مرا به خاطر بسپار') }}
        </label>

        <button type="submit" class="w-full rounded-lg bg-pine px-4 py-2.5 text-sm font-medium text-white transition hover:bg-pine-600">{{ __('ورود') }}</button>
    </form>
@endsection
