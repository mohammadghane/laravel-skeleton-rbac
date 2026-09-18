<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/**
 * Include this file from routes/web.php:
 *   require __DIR__.'/acl.php';
 */
Route::middleware(['web', 'auth'])->prefix('admin')->name('admin.')->group(function () {

    /**
     * صفحه‌ی فرود پس از ورود. اولویت با داشبورد اضافه‌کار است (تنها داشبورد واقعی
     * پروژه)؛ اگر کاربر به آن دسترسی نداشت، به فهرست کاربران و در نهایت پروفایل خودش
     * هدایت می‌شود.
     *
     * `/admin/dashboard` هم به‌عنوان نام آشنا پذیرفته می‌شود تا کاربری که مستقیم آن را
     * تایپ می‌کند به ۴۰۴ نخورد.
     */
    $landing = function (\Illuminate\Http\Request $request) {
        $user = $request->user();

        // مبنا همان دسترسیِ منوهاست (config/permissions.php) تا صفحه‌ی فرود دقیقاً با
        // چیزی که کاربر در سایدبار می‌بیند هم‌خوان باشد. مدیر واحد و معاون هم بدون
        // دسترسی صریح به داشبورد می‌روند، چون بخشی از وظیفه‌ی اجرایی‌شان است.
        if ($user->hasPermission('menu/dashboard') || $user->isUnitManager() || $user->isDeputyHead()) {
            return redirect()->route('admin.overtime.dashboard');
        }

        if ($user->hasPermission('menu/overtime-entry')) {
            return redirect()->route('admin.overtime.periods.index');
        }

        return $user->hasPermission('menu/personnel')
            ? redirect()->route('admin.users.index')
            : redirect()->route('admin.profile');
    };

    Route::get('/', $landing)->name('dashboard');
    Route::get('/dashboard', $landing)->name('dashboard.alias');

    Route::prefix('roles')->name('roles.')->middleware('permission:menu/roles')->group(function () {
        Route::get('/', [RoleController::class, 'index'])->name('index');
        Route::get('/create', [RoleController::class, 'edit'])->name('create');
        Route::get('/{role}/edit', [RoleController::class, 'edit'])->name('edit');
        Route::post('/', [RoleController::class, 'store'])->name('store');
        Route::delete('/{role}', [RoleController::class, 'destroy'])->name('destroy');
        Route::get('/{role}/permissions', [RoleController::class, 'permissions'])->name('permissions');
        Route::post('/{role}/permissions', [RoleController::class, 'savePermissions'])->name('permissions.store');
    });

    Route::prefix('users')->name('users.')->middleware('permission:menu/personnel')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/create', [UserController::class, 'edit'])->name('create');
        Route::get('/{user}/edit', [UserController::class, 'edit'])->name('edit');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::post('/bulk-destroy', [UserController::class, 'bulkDestroy'])->name('bulk-destroy');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
    });

    // Every authenticated user (not just admin/users holders) can manage their own profile.
    Route::get('/profile', [UserController::class, 'profile'])->name('profile');
    Route::post('/profile', [UserController::class, 'store'])->name('profile.store');
    Route::get('/users/check-unique', [UserController::class, 'checkUnique'])->name('users.check-unique');

    Route::prefix('locations')->name('locations.')->middleware('permission:menu/locations')->group(function () {
        Route::get('/', [LocationController::class, 'index'])->name('index');
        Route::get('/create', [LocationController::class, 'edit'])->name('create');
        Route::get('/{location}/edit', [LocationController::class, 'edit'])->name('edit');
        Route::post('/', [LocationController::class, 'store'])->name('store');
        Route::post('/bulk-destroy', [LocationController::class, 'bulkDestroy'])->name('bulk-destroy');
        Route::put('/{location}', [LocationController::class, 'update'])->name('update');
        Route::delete('/{location}', [LocationController::class, 'destroy'])->name('destroy');
    });

    Route::get('/audit-logs', [AuditLogController::class, 'index'])
        ->name('audit-logs.index')
        ->middleware('permission:menu/audit-logs');
});
