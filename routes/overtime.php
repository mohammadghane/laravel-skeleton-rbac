<?php

use App\Http\Controllers\Admin\Overtime\ContractTypeCeilingController;
use App\Http\Controllers\Admin\Overtime\ContractTypeController;
use App\Http\Controllers\Admin\Overtime\DeputyController;
use App\Http\Controllers\Admin\Overtime\OvertimeExportController;
use App\Http\Controllers\Admin\Overtime\OvertimePeriodController;
use App\Http\Controllers\Admin\Overtime\OvertimeReportController;
use App\Http\Controllers\Admin\Overtime\PersonnelSpecialItemController;
use App\Http\Controllers\Admin\Overtime\PositionCeilingController;
use App\Http\Controllers\Admin\Overtime\PositionController;
use App\Http\Controllers\Admin\Overtime\SpecialItemController;
use App\Http\Controllers\Admin\Overtime\SpecialItemValueController;
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

    // --- چارت سازمانی: معاونت/واحد/سمت/نوع قرارداد ---
    Route::middleware('permission:overtime/org-chart-manage')->group(function () {
        Route::apiResource('deputies', DeputyController::class)->except(['show']);
        Route::apiResource('units', UnitController::class)->except(['show']);
        Route::apiResource('positions', PositionController::class)->except(['show']);
        Route::apiResource('contract-types', ContractTypeController::class)->except(['show']);
    });

    // --- پارامترهای HR: سقف‌های ماهانه، آیتم‌های ویژه، خروجی مالی ---
    Route::middleware('permission:overtime/hr-manage')->group(function () {
        Route::get('contract-types/{contractType}/ceilings', [ContractTypeCeilingController::class, 'index'])->name('contract-types.ceilings.index');
        Route::post('contract-types/{contractType}/ceilings', [ContractTypeCeilingController::class, 'store'])->name('contract-types.ceilings.store');

        Route::get('positions/{position}/ceilings', [PositionCeilingController::class, 'index'])->name('positions.ceilings.index');
        Route::post('positions/{position}/ceilings', [PositionCeilingController::class, 'store'])->name('positions.ceilings.store');

        Route::apiResource('special-items', SpecialItemController::class)->except(['show']);
        Route::get('personnel/{personnel}/special-items', [PersonnelSpecialItemController::class, 'index'])->name('personnel.special-items.index');
        Route::put('personnel/{personnel}/special-items/{specialItem}', [PersonnelSpecialItemController::class, 'update'])->name('personnel.special-items.update');
        Route::post('personnel/{personnel}/special-items/{specialItem}/value', [SpecialItemValueController::class, 'store'])->name('personnel.special-items.value');

        Route::post('personnel/{personnel}/special-items/{specialItem}/apply', [OvertimePeriodController::class, 'applyHrSpecialItem'])->name('periods.special-items.apply-hr');
        Route::post('personnel/{personnel}/financial-params', [OvertimePeriodController::class, 'updateFinancialParams'])->name('personnel.financial-params.update');

        Route::get('reports/export', [OvertimeExportController::class, 'financial'])->name('reports.export');
    });

    // --- گردش کار اضافه‌کار (مالکیت per-record داخل خودِ کنترلر چک می‌شود: مدیر واحد/معاون/مدیرعامل) ---
    Route::get('periods', [OvertimePeriodController::class, 'index'])->name('periods.index');
    Route::post('personnel/{personnel}/normal', [OvertimePeriodController::class, 'storeNormal'])->name('periods.normal.store');
    Route::post('periods/{period}/submit', [OvertimePeriodController::class, 'submit'])->name('periods.submit');
    Route::post('periods/{period}/deputy-review', [OvertimePeriodController::class, 'deputyReview'])->name('periods.deputy-review');
    Route::post('periods/{period}/ceo-review', [OvertimePeriodController::class, 'ceoReview'])->name('periods.ceo-review');
    Route::post('periods/{period}/special-items/{specialItem}', [OvertimePeriodController::class, 'applySpecialItem'])->name('periods.special-items.apply');

    // --- گزارش‌گیری و داشبورد (بند ۴.۷ شرح خدمات) - کنترل دسترسی per-scope داخل کنترلر ---
    Route::get('reports/consumption', [OvertimeReportController::class, 'consumption'])->name('reports.consumption');
    Route::get('reports/personnel', [OvertimeReportController::class, 'personnel'])->name('reports.personnel');
    Route::get('reports/missing', [OvertimeReportController::class, 'missing'])->name('reports.missing');
    Route::get('reports/warnings', [OvertimeReportController::class, 'warnings'])->name('reports.warnings');
    Route::get('reports/deputy-comparison', [OvertimeReportController::class, 'deputyComparison'])->name('reports.deputy-comparison');
    Route::get('reports/ranking', [OvertimeReportController::class, 'ranking'])->name('reports.ranking');
    Route::get('reports/history', [OvertimeReportController::class, 'history'])->name('reports.history');
});
