<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage in routes:  Route::get(...)->middleware('permission:admin/users')
 * Equivalent to the old MY_Controller-level isAllow('admin/users') checks.
 */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string $privilege): Response
    {
        abort_unless(
            $request->user() && $request->user()->hasPermission($privilege),
            403,
            __('شما به این بخش دسترسی ندارید.')
        );

        return $next($request);
    }
}
