<?php

use Illuminate\Support\Facades\Route;

require __DIR__.'/auth.php';
require __DIR__.'/acl.php';
require __DIR__.'/settings.php';
require __DIR__.'/locale.php';
require __DIR__.'/overtime.php';

Route::get('/', function () {
    return view('welcome');
});
