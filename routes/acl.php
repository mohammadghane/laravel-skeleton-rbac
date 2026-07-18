<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/**
 * Include this file from routes/web.php:
 *   require __DIR__.'/acl.php';
 */
Route::middleware(['web', 'auth'])->prefix('admin')->name('admin.')->group(function () {

    // Generic post-login landing spot: send admins to the users grid, everyone
    // else to their own profile. Swap for a real dashboard view when you have one.
    Route::get('/', function (\Illuminate\Http\Request $request) {
        return $request->user()->hasPermission('admin/users')
            ? redirect()->route('admin.users.index')
            : redirect()->route('admin.profile');
    })->name('dashboard');

    Route::prefix('roles')->name('roles.')->middleware('permission:admin/roles')->group(function () {
        Route::get('/', [RoleController::class, 'index'])->name('index');
        Route::get('/create', [RoleController::class, 'edit'])->name('create');
        Route::get('/{role}/edit', [RoleController::class, 'edit'])->name('edit');
        Route::post('/', [RoleController::class, 'store'])->name('store');
        Route::delete('/{role}', [RoleController::class, 'destroy'])->name('destroy');
        Route::get('/{role}/permissions', [RoleController::class, 'permissions'])->name('permissions');
        Route::post('/{role}/permissions', [RoleController::class, 'savePermissions'])->name('permissions.store');
    });

    Route::prefix('users')->name('users.')->middleware('permission:admin/users')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/create', [UserController::class, 'edit'])->name('create');
        Route::get('/{user}/edit', [UserController::class, 'edit'])->name('edit');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
    });

    // Every authenticated user (not just admin/users holders) can manage their own profile.
    Route::get('/profile', [UserController::class, 'profile'])->name('profile');
    Route::post('/profile', [UserController::class, 'store'])->name('profile.store');
    Route::get('/users/check-unique', [UserController::class, 'checkUnique'])->name('users.check-unique');

    Route::get('/audit-logs', [AuditLogController::class, 'index'])
        ->name('audit-logs.index')
        ->middleware('permission:admin/audit-logs');
});
