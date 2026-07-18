<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale');
        $available = array_keys(config('locales.available'));

        if ($locale && in_array($locale, $available, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
