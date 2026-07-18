<?php

use App\Http\Controllers\Admin\SettingsController;
use Illuminate\Support\Facades\Route;

/**
 * Include this file from routes/web.php:
 *   require __DIR__.'/settings.php';
 */
Route::middleware(['web', 'auth'])->prefix('admin')->name('admin.')->group(function () {

    // Self-service: any authenticated user can edit their own center's 'general' settings.
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SettingsController::class, 'edit'])->name('edit');
        Route::post('/', [SettingsController::class, 'store'])->name('store');
    });

    // Privileged: editing another center's full settings (every group, not just 'general').
    Route::prefix('centers/{centerId}/settings')->name('settings.center.')->middleware('permission:admin/settings')->group(function () {
        Route::get('/', [SettingsController::class, 'centerEdit'])->name('edit');
        Route::post('/', [SettingsController::class, 'centerStore'])->name('store');
    });
});
