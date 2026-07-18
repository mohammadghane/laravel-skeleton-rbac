<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Include this file from routes/web.php:
 *   require __DIR__.'/locale.php';
 */
Route::get('/locale/{locale}', function (Request $request, string $locale) {
    $available = array_keys(config('locales.available'));

    abort_unless(in_array($locale, $available, true), 404);

    $request->session()->put('locale', $locale);

    return redirect()->back();
})->name('locale.switch');
