<?php

use Illuminate\Support\Facades\Route;

require __DIR__.'/auth.php';
require __DIR__.'/acl.php';
require __DIR__.'/settings.php';
require __DIR__.'/locale.php';
require __DIR__.'/overtime.php';

// ریشه‌ی سایت: کاربر واردنشده به صفحه‌ی ورود و کاربر واردشده به صفحه‌ی فرود پنل
// می‌رود. صفحه‌ی welcome پیش‌فرض لاراول در این پروژه کاربردی ندارد.
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('admin.dashboard')
        : redirect()->route('login');
})->name('home');

// میان‌بر آشنا: /dashboard را به صفحه‌ی فرود پنل می‌فرستد (که خودش بسته به دسترسی کاربر،
// داشبورد اضافه‌کار یا فهرست کاربران یا پروفایل را نشان می‌دهد). بدون این، کاربری که
// مستقیم /dashboard را تایپ می‌کند خطای ۴۰۴ می‌گیرد.
Route::middleware(['web', 'auth'])->get('/dashboard', fn () => redirect()->route('admin.dashboard'))->name('dashboard');
