<?php

use App\Http\Controllers\Admin\Overtime\ContractTypeCeilingController;
use App\Http\Controllers\Admin\Overtime\ContractTypeController;
use App\Http\Controllers\Admin\Overtime\DeputyCapacityTransferController;
use App\Http\Controllers\Admin\Overtime\DeputyController;
use App\Http\Controllers\Admin\Overtime\OvertimeExportController;
use App\Http\Controllers\Admin\Overtime\OvertimeFinancialParamsController;
use App\Http\Controllers\Admin\Overtime\OvertimeImportController;
use App\Http\Controllers\Admin\Overtime\OvertimePeriodController;
use App\Http\Controllers\Admin\Overtime\OvertimeReportController;
use App\Http\Controllers\Admin\Overtime\PersonnelSyncController;
use App\Http\Controllers\Admin\Overtime\PersonnelSpecialItemController;
use App\Http\Controllers\Admin\Overtime\PositionCeilingController;
use App\Http\Controllers\Admin\Overtime\PositionController;
use App\Http\Controllers\Admin\Overtime\SpecialItemController;
use App\Http\Controllers\Admin\Overtime\UnitController;
use Illuminate\Support\Facades\Route;

/**
 * Include this file from routes/web.php:
 *   require __DIR__.'/overtime.php';
 *
 * الگوی دقیق routes/acl.php: خودِ این فایل گروه/پیشوند/میدل‌ور را تعریف می‌کند. چون
 * app/Http/Controllers/Controller.php در این پروژه خالی است (بدون متد middleware())،
 * برخلاف نسخه‌ی اول این فایل، permission:... اینجا روی خودِ گروه‌های route اعمال شده،
 * نه در constructor کنترلرها (که فاتال ارور می‌داد).
 */
Route::middleware(['web', 'auth'])->prefix('admin/overtime')->name('admin.overtime.')->group(function () {

    // --- چارت سازمانی ---
    // هر منو دسترسی مستقل خودش را دارد (menu/*)؛ ویرایش داده در همه‌ی آن‌ها علاوه بر
    // دسترسیِ منو نیازمند overtime/org-chart-manage است، تا بتوان نقشی ساخت که فقط
    // ببیند و ویرایش نکند.
    Route::middleware('permission:menu/deputies')->group(function () {
        Route::get('deputies', [DeputyController::class, 'index'])->name('deputies.index');

        Route::middleware('permission:overtime/org-chart-manage')->group(function () {
            Route::get('deputies/create', [DeputyController::class, 'edit'])->name('deputies.create');
            Route::get('deputies/{deputy}/edit', [DeputyController::class, 'edit'])->name('deputies.edit');
            Route::post('deputies/bulk-destroy', [DeputyController::class, 'bulkDestroy'])->name('deputies.bulk-destroy');
            Route::post('deputies', [DeputyController::class, 'store'])->name('deputies.store');
            Route::put('deputies/{deputy}', [DeputyController::class, 'update'])->name('deputies.update');
            Route::delete('deputies/{deputy}', [DeputyController::class, 'destroy'])->name('deputies.destroy');
        });
    });

    Route::middleware('permission:menu/units')->group(function () {
        Route::get('units', [UnitController::class, 'index'])->name('units.index');

        Route::middleware('permission:overtime/org-chart-manage')->group(function () {
            Route::get('units/create', [UnitController::class, 'edit'])->name('units.create');
            Route::get('units/{unit}/edit', [UnitController::class, 'edit'])->name('units.edit');
            Route::post('units/bulk-destroy', [UnitController::class, 'bulkDestroy'])->name('units.bulk-destroy');
            Route::post('units', [UnitController::class, 'store'])->name('units.store');
            Route::put('units/{unit}', [UnitController::class, 'update'])->name('units.update');
            Route::delete('units/{unit}', [UnitController::class, 'destroy'])->name('units.destroy');
        });
    });

    Route::middleware('permission:menu/positions')->group(function () {
        Route::get('positions', [PositionController::class, 'index'])->name('positions.index');

        // ثبت سقف مازاد سمت - در اختیار منابع انسانی، نه ویرایش‌کننده‌ی چارت.
        Route::middleware('permission:overtime/hr-manage')->group(function () {
            Route::post('positions/ceilings/bulk', [PositionCeilingController::class, 'bulkStore'])->name('positions.ceilings.bulk');
            Route::get('positions/{position}/ceilings', [PositionCeilingController::class, 'index'])->name('positions.ceilings.index');
            Route::post('positions/{position}/ceilings', [PositionCeilingController::class, 'store'])->name('positions.ceilings.store');
        });

        Route::middleware('permission:overtime/org-chart-manage')->group(function () {
            Route::get('positions/create', [PositionController::class, 'edit'])->name('positions.create');
            Route::get('positions/{position}/edit', [PositionController::class, 'edit'])->name('positions.edit');
            Route::post('positions/bulk-destroy', [PositionController::class, 'bulkDestroy'])->name('positions.bulk-destroy');
            Route::post('positions', [PositionController::class, 'store'])->name('positions.store');
            Route::put('positions/{position}', [PositionController::class, 'update'])->name('positions.update');
            Route::delete('positions/{position}', [PositionController::class, 'destroy'])->name('positions.destroy');
        });
    });

    Route::middleware('permission:menu/contract-types')->group(function () {
        Route::get('contract-types', [ContractTypeController::class, 'index'])->name('contract-types.index');

        Route::middleware('permission:overtime/hr-manage')->group(function () {
            // ویرایش گروهی سقف - باید پیش از مسیر پارامتری زیر بیاید.
            Route::post('contract-types/ceilings/bulk', [ContractTypeCeilingController::class, 'bulkStore'])->name('contract-types.ceilings.bulk');
            Route::get('contract-types/{contractType}/ceilings', [ContractTypeCeilingController::class, 'index'])->name('contract-types.ceilings.index');
            Route::post('contract-types/{contractType}/ceilings', [ContractTypeCeilingController::class, 'store'])->name('contract-types.ceilings.store');
        });

        Route::middleware('permission:overtime/org-chart-manage')->group(function () {
            Route::get('contract-types/create', [ContractTypeController::class, 'edit'])->name('contract-types.create');
            Route::get('contract-types/{contractType}/edit', [ContractTypeController::class, 'edit'])->name('contract-types.edit');
            Route::post('contract-types/bulk-destroy', [ContractTypeController::class, 'bulkDestroy'])->name('contract-types.bulk-destroy');
            Route::post('contract-types', [ContractTypeController::class, 'store'])->name('contract-types.store');
            Route::put('contract-types/{contractType}', [ContractTypeController::class, 'update'])->name('contract-types.update');
            Route::delete('contract-types/{contractType}', [ContractTypeController::class, 'destroy'])->name('contract-types.destroy');
        });
    });

    // --- آیتم‌های ویژه ---
    Route::middleware('permission:menu/special-items')->group(function () {
        Route::get('special-items', [SpecialItemController::class, 'index'])->name('special-items.index');

        Route::middleware('permission:overtime/hr-manage')->group(function () {
            Route::get('special-items/create', [SpecialItemController::class, 'edit'])->name('special-items.create');
            Route::get('special-items/{specialItem}/edit', [SpecialItemController::class, 'edit'])->name('special-items.edit');
            Route::get('special-items/{specialItem}/personnel', [SpecialItemController::class, 'personnel'])->name('special-items.personnel');
            Route::put('special-items/{specialItem}/personnel', [SpecialItemController::class, 'syncPersonnel'])->name('special-items.personnel.sync');
            Route::post('special-items/bulk-destroy', [SpecialItemController::class, 'bulkDestroy'])->name('special-items.bulk-destroy');
            Route::post('special-items', [SpecialItemController::class, 'store'])->name('special-items.store');
            Route::put('special-items/{specialItem}', [SpecialItemController::class, 'update'])->name('special-items.update');
            Route::delete('special-items/{specialItem}', [SpecialItemController::class, 'destroy'])->name('special-items.destroy');
        });
    });

    // --- کسر کار / ماموریت (ایمپورت اکسل -) ---
    Route::middleware(['permission:menu/import', 'permission:overtime/hr-manage'])->group(function () {
        Route::get('import', [OvertimeImportController::class, 'form'])->name('import.form');
        Route::post('import/deduction', [OvertimeImportController::class, 'deduction'])->name('import.deduction');
        Route::post('import/mission', [OvertimeImportController::class, 'mission'])->name('import.mission');
    });

    // --- همگام‌سازی پرسنل از وب‌سرویس ---
    Route::middleware(['permission:menu/personnel-sync', 'permission:overtime/hr-manage'])->group(function () {
        Route::get('sync', [PersonnelSyncController::class, 'index'])->name('sync.index');
        Route::post('sync', [PersonnelSyncController::class, 'store'])->name('sync.store');
    });

    // --- سایر عملیات منابع انسانی (بدون منوی اختصاصی) ---
    Route::middleware('permission:overtime/hr-manage')->group(function () {
        Route::get('personnel/{personnel}/special-items', [PersonnelSpecialItemController::class, 'index'])->name('personnel.special-items.index');
        Route::put('personnel/{personnel}/special-items/{specialItem}', [PersonnelSpecialItemController::class, 'update'])->name('personnel.special-items.update');
        Route::post('personnel/{personnel}/special-items/{specialItem}/apply', [OvertimePeriodController::class, 'applyHrSpecialItem'])->name('periods.special-items.apply-hr');
        Route::post('personnel/{personnel}/financial-params', [OvertimePeriodController::class, 'updateFinancialParams'])->name('personnel.financial-params.update');

        // صفحه تنظیم پارامترهای مالی - فقط برای مدیر منابع انسانی
        Route::get('financial-params', [OvertimeFinancialParamsController::class, 'index'])->name('financial-params.index');
        Route::get('financial-params/{personnel}', [OvertimeFinancialParamsController::class, 'edit'])->name('financial-params.edit');
        Route::post('financial-params/bulk', [OvertimeFinancialParamsController::class, 'bulkStore'])->name('financial-params.bulk-store');

        Route::get('reports/export', [OvertimeExportController::class, 'financial'])->name('reports.export');
    });

    // --- گردش کار اضافه‌کار ---
    // menu-or-chart: دسترسی منو، یا نقش اجرایی در چارت (مدیر واحد/معاون) که ناگزیر به
    // این بخش نیاز دارد. مالکیت per-record جداگانه داخل کنترلر بررسی می‌شود.
    Route::middleware('menu-or-chart:menu/overtime-entry')->group(function () {
        Route::get('periods', [OvertimePeriodController::class, 'index'])->name('periods.index');
        Route::get('periods/personnel/{personnel}', [OvertimePeriodController::class, 'show'])->name('periods.show');
        // ثبت گروهی برای همه‌ی زیرمجموعه - باید پیش از مسیر پارامتری بیاید.
        Route::post('periods/bulk-normal', [OvertimePeriodController::class, 'bulkStoreNormal'])->name('periods.bulk-normal');
        Route::post('personnel/{personnel}/normal', [OvertimePeriodController::class, 'storeNormal'])->name('periods.normal.store');
        // ارسال گروهی به معاونت - باید پیش از مسیر پارامتری بیاید.
        Route::post('periods/bulk-submit', [OvertimePeriodController::class, 'bulkSubmit'])->name('periods.bulk-submit');
        Route::post('periods/{period}/submit', [OvertimePeriodController::class, 'submit'])->name('periods.submit');
        Route::put('periods/{period}/excess', [OvertimePeriodController::class, 'storeExcess'])->name('periods.excess.store');
        Route::post('periods/{period}/deputy-review', [OvertimePeriodController::class, 'deputyReview'])->name('periods.deputy-review');
        Route::post('periods/{period}/ceo-review', [OvertimePeriodController::class, 'ceoReview'])->name('periods.ceo-review');
        Route::post('periods/{period}/special-items/{specialItem}', [OvertimePeriodController::class, 'applySpecialItem'])->name('periods.special-items.apply');
    });

    // --- واگذاری ظرفیت مازاد بین معاونت‌ها ---
    // معاون بدون نیاز به دسترسی منو هم باید بتواند از ظرفیت معاونت خودش واگذار کند؛
    // تفکیک HR/معاون داخل کنترلر انجام می‌شود.
    Route::middleware('menu-or-chart:menu/capacity-transfers,deputy')->group(function () {
        Route::get('capacity-transfers', [DeputyCapacityTransferController::class, 'index'])->name('capacity-transfers.index');
        Route::post('capacity-transfers', [DeputyCapacityTransferController::class, 'store'])->name('capacity-transfers.store');
        Route::delete('capacity-transfers/{transfer}', [DeputyCapacityTransferController::class, 'destroy'])->name('capacity-transfers.destroy');
    });

    // --- گزارش‌گیری و داشبورد - محدوده‌ی داده per-scope داخل کنترلر ---
    Route::middleware('menu-or-chart:menu/dashboard')->group(function () {
        Route::get('dashboard', [OvertimeReportController::class, 'dashboard'])->name('dashboard');
        Route::get('reports/personnel-monthly', [OvertimeReportController::class, 'personnelPage'])->name('reports.personnel-monthly');

        // نقاط پایانی JSON (برای مصرف برنامه‌نویسی/نمودارها):
        Route::get('reports/consumption', [OvertimeReportController::class, 'consumption'])->name('reports.consumption');
        Route::get('reports/personnel', [OvertimeReportController::class, 'personnel'])->name('reports.personnel');
        Route::get('reports/missing', [OvertimeReportController::class, 'missing'])->name('reports.missing');
        Route::get('reports/warnings', [OvertimeReportController::class, 'warnings'])->name('reports.warnings');
        Route::get('reports/deputy-comparison', [OvertimeReportController::class, 'deputyComparison'])->name('reports.deputy-comparison');
        Route::get('reports/ranking', [OvertimeReportController::class, 'ranking'])->name('reports.ranking');
        Route::get('reports/history', [OvertimeReportController::class, 'history'])->name('reports.history');
    });
});
