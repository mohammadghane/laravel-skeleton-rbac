<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// هر روز ساعت ۸ صبح، وضعیت سقف اضافه‌کار ماه جاری را چک و در صورت نیاز به HR/مدیرعامل هشدار می‌فرستد.
Schedule::command('overtime:send-ceiling-warnings')->dailyAt('08:00');
