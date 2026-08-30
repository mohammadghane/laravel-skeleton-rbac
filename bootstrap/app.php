<?php
use App\Http\Middleware\NormalizePersianInput;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\SetLocale;


return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'permission' => \App\Http\Middleware\CheckPermission::class,
            // دسترسی منو یا نقش چارت سازمانی - نگاه کنید به توضیح خودِ کلاس.
            'menu-or-chart' => \App\Http\Middleware\CheckMenuOrChartRole::class,
        ]);
        // NormalizePersianInput پیش از SetLocale می‌آید تا هر ورودی (فرم، جست‌وجو، فیلتر)
        // با حروف/ارقام یکسان به کنترلر و اعتبارسنجی برسد.
        $middleware->web(append: [NormalizePersianInput::class, SetLocale::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
