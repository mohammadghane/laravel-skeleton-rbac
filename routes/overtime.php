<?php

use App\Http\Controllers\Admin\Overtime\ContractTypeCeilingController;
use App\Http\Controllers\Admin\Overtime\ContractTypeController;
use App\Http\Controllers\Admin\Overtime\DeputyCapacityTransferController;
use App\Http\Controllers\Admin\Overtime\DeputyController;
use App\Http\Controllers\Admin\Overtime\OvertimeExportController;
use App\Http\Controllers\Admin\Overtime\OvertimeImportController;
use App\Http\Controllers\Admin\Overtime\OvertimePeriodController;
use App\Http\Controllers\Admin\Overtime\OvertimeReportController;
use App\Http\Controllers\Admin\Overtime\PersonnelSyncController;
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
    // مسیرهای create/edit جدا تعریف شده‌اند چون فرم داخل مودال مشترک پنل رندر می‌شود
    // (همان الگوی routes/acl.php برای کاربران و نقش‌ها).
    Route::middleware('permission:overtime/org-chart-manage')->group(function () {
        Route::get('deputies/create', [DeputyController::class, 'edit'])->name('deputies.create');
        Route::get('deputies/{deputy}/edit', [DeputyController::class, 'edit'])->name('deputies.edit');
        Route::apiResource('deputies', DeputyController::class)->except(['show']);

        Route::get('units/create', [UnitController::class, 'edit'])->name('units.create');
        Route::get('units/{unit}/edit', [UnitController::class, 'edit'])->name('units.edit');
        Route::apiResource('units', UnitController::class)->except(['show']);

        Route::get('positions/create', [PositionController::class, 'edit'])->name('positions.create');
        Route::get('positions/{position}/edit', [PositionController::class, 'edit'])->name('positions.edit');
        Route::apiResource('positions', PositionController::class)->except(['show']);

        Route::get('contract-types/create', [ContractTypeController::class, 'edit'])->name('contract-types.create');
        Route::get('contract-types/{contractType}/edit', [ContractTypeController::class, 'edit'])->name('contract-types.edit');
        Route::apiResource('contract-types', ContractTypeController::class)->except(['show']);
    });

    // --- پارامترهای HR: سقف‌های ماهانه، آیتم‌های ویژه، خروجی مالی ---
    Route::middleware('permission:overtime/hr-manage')->group(function () {
        // ویرایش گروهی سقف چند نوع قرارداد - باید پیش از مسیر پارامتری زیر بیاید.
        Route::post('contract-types/ceilings/bulk', [ContractTypeCeilingController::class, 'bulkStore'])->name('contract-types.ceilings.bulk');

        Route::get('contract-types/{contractType}/ceilings', [ContractTypeCeilingController::class, 'index'])->name('contract-types.ceilings.index');
        Route::post('contract-types/{contractType}/ceilings', [ContractTypeCeilingController::class, 'store'])->name('contract-types.ceilings.store');

        Route::post('positions/ceilings/bulk', [PositionCeilingController::class, 'bulkStore'])->name('positions.ceilings.bulk');

        Route::get('positions/{position}/ceilings', [PositionCeilingController::class, 'index'])->name('positions.ceilings.index');
        Route::post('positions/{position}/ceilings', [PositionCeilingController::class, 'store'])->name('positions.ceilings.store');

        Route::get('special-items/create', [SpecialItemController::class, 'edit'])->name('special-items.create');
        Route::get('special-items/{specialItem}/edit', [SpecialItemController::class, 'edit'])->name('special-items.edit');
        Route::apiResource('special-items', SpecialItemController::class)->except(['show']);
        Route::get('personnel/{personnel}/special-items', [PersonnelSpecialItemController::class, 'index'])->name('personnel.special-items.index');
        Route::put('personnel/{personnel}/special-items/{specialItem}', [PersonnelSpecialItemController::class, 'update'])->name('personnel.special-items.update');
        Route::post('personnel/{personnel}/special-items/{specialItem}/value', [SpecialItemValueController::class, 'store'])->name('personnel.special-items.value');

        Route::post('personnel/{personnel}/special-items/{specialItem}/apply', [OvertimePeriodController::class, 'applyHrSpecialItem'])->name('periods.special-items.apply-hr');
        Route::post('personnel/{personnel}/financial-params', [OvertimePeriodController::class, 'updateFinancialParams'])->name('personnel.financial-params.update');

        // ایمپورت اکسل پارامترهای مستقل - بند ۴.۲: فقط از طریق فایل اکسل.
        // هر پارامتر مسیر مجزای خودش را دارد (دو فرم جداگانه در UI).
        Route::get('import', [OvertimeImportController::class, 'form'])->name('import.form');
        Route::post('import/deduction', [OvertimeImportController::class, 'deduction'])->name('import.deduction');
        Route::post('import/mission', [OvertimeImportController::class, 'mission'])->name('import.mission');

        // همگام‌سازی پرسنل/قرارداد/سمت از وب‌سرویس پرسنلی سازمان
        Route::get('sync', [PersonnelSyncController::class, 'index'])->name('sync.index');
        Route::post('sync', [PersonnelSyncController::class, 'store'])->name('sync.store');

        Route::get('reports/export', [OvertimeExportController::class, 'financial'])->name('reports.export');
    });

    // --- گردش کار اضافه‌کار (مالکیت per-record داخل خودِ کنترلر چک می‌شود: مدیر واحد/معاون/مدیرعامل) ---
    Route::get('periods', [OvertimePeriodController::class, 'index'])->name('periods.index');
    Route::get('periods/personnel/{personnel}', [OvertimePeriodController::class, 'show'])->name('periods.show');
    Route::post('personnel/{personnel}/normal', [OvertimePeriodController::class, 'storeNormal'])->name('periods.normal.store');
    Route::post('periods/{period}/submit', [OvertimePeriodController::class, 'submit'])->name('periods.submit');
    Route::post('periods/{period}/deputy-review', [OvertimePeriodController::class, 'deputyReview'])->name('periods.deputy-review');
    Route::post('periods/{period}/ceo-review', [OvertimePeriodController::class, 'ceoReview'])->name('periods.ceo-review');
    Route::post('periods/{period}/special-items/{specialItem}', [OvertimePeriodController::class, 'applySpecialItem'])->name('periods.special-items.apply');

    // --- واگذاری ظرفیت مازاد بین معاونت‌ها (تبصره‌ی اول بازنگری بند ۴.۲) ---
    // خارج از گروه hr-manage است چون خودِ معاون هم باید بتواند از ظرفیت معاونتش واگذار
    // کند؛ تفکیک دسترسی HR/معاون داخل کنترلر انجام می‌شود.
    Route::get('capacity-transfers', [DeputyCapacityTransferController::class, 'index'])->name('capacity-transfers.index');
    Route::post('capacity-transfers', [DeputyCapacityTransferController::class, 'store'])->name('capacity-transfers.store');
    Route::delete('capacity-transfers/{transfer}', [DeputyCapacityTransferController::class, 'destroy'])->name('capacity-transfers.destroy');

    // --- گزارش‌گیری و داشبورد (بند ۴.۷ شرح خدمات) - کنترل دسترسی per-scope داخل کنترلر ---
    // صفحات HTML:
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
