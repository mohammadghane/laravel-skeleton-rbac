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

        $canSeeOvertimeDashboard = $user->hasPermission('overtime/hr-manage')
            || $user->hasPermission('overtime/ceo-approve')
            || $user->hasPermission('overtime/reports')
            || $user->isUnitManager()
            || $user->isDeputyHead();

        if ($canSeeOvertimeDashboard) {
            return redirect()->route('admin.overtime.dashboard');
        }

        return $user->hasPermission('admin/users')
            ? redirect()->route('admin.users.index')
            : redirect()->route('admin.profile');
    };

    Route::get('/', $landing)->name('dashboard');
    Route::get('/dashboard', $landing)->name('dashboard.alias');

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
