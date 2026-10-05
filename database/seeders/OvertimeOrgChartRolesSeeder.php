<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * نقش‌های سامانه مدیریت اضافه‌کار پرسنل:
 * - مدیر منابع انسانی (HR)
 * - مدیر واحد
 * - معاون
 * - مدیرعامل
 * 
 * این نقش‌ها با استفاده از سیستم دسترسی‌های سازمانی (HasRoles trait) کار می‌کنند.
 * نام انگلیسی این نقش‌ها به‌صورت داخلی در کد استفاده می‌شود.
 */
class OvertimeOrgChartRolesSeeder extends Seeder
{
    public function run(): void
    {
        // ۱. مدیر منابع انسانی (HR)
        // دسترسی کامل به مدیریت پارامترهای اضافه‌کار
        $hrRole = Role::updateOrCreate(
            ['name' => 'مدیر منابع انسانی'],
            ['desk' => 'مدیریت پارامترهای اضافه‌کار، تعریف آیتم‌های ویژه، ورود اطلاعات ماموریت و کسر کار', 'hidden' => false, 'is_default' => false]
        );

        $hrPermissions = [
            'overtime/hr-manage',        // مدیریت پارامترهای اضافه‌کار
            'overtime/reports',          // مشاهده گزارش‌ها
            'overtime/org-chart-manage', // ویرایش چارت سازمانی
            'admin/users',               // ویرایش پرسنل (برای تخصیص واحد/معاونت)
            'admin/roles',               // ویرایش نقش‌ها
            'admin/audit-logs',          // مشاهده گزارش تغییرات
            'menu/dashboard',
            'menu/personnel',
            'menu/deputies',
            'menu/positions',
            'menu/units',
            'menu/contract-types',
            'menu/import',
            'menu/financial-params',     // پارامترهای مالی
            'menu/special-items',
            'menu/capacity-transfers',
            'menu/overtime-entry',
            'menu/roles',
            'menu/audit-logs',
        ];

        $hrRole->syncPermissions(
            collect($hrPermissions)->map(fn ($key) => ['key' => $key])->toArray()
        );

        // ۲. مدیر واحد
        // مسئول ثبت اضافه‌کار عادی برای زیرمجموعه‌ها
        $managerRole = Role::updateOrCreate(
            ['name' => 'مدیر واحد'],
            ['desk' => 'ثبت و مدیریت اضافه‌کار عادی برای پرسنل زیرمجموعه، ارسال به معاونت', 'hidden' => false, 'is_default' => false]
        );

        $managerPermissions = [
            'menu/dashboard',
            'menu/overtime-entry',
            'menu/personnel',
            'menu/deputies',
            'menu/positions',
            'menu/units',
            'menu/contract-types',
        ];

        $managerRole->syncPermissions(
            collect($managerPermissions)->map(fn ($key) => ['key' => $key])->toArray()
        );

        // ۳. معاون
        // مسئول بررسی و تایید اضافه‌کار، ثبت اضافه‌کار مازاد
        $deputyRole = Role::updateOrCreate(
            ['name' => 'معاون'],
            ['desk' => 'بررسی و تایید اضافه‌کار، ثبت اضافه‌کار مازاد، واگذاری ظرفیت به معاونت‌های دیگر', 'hidden' => false, 'is_default' => false]
        );

        $deputyPermissions = [
            'menu/dashboard',
            'menu/overtime-entry',
            'menu/personnel',
            'menu/deputies',
            'menu/positions',
            'menu/units',
            'menu/contract-types',
            'menu/capacity-transfers',
            'menu/reports',
        ];

        $deputyRole->syncPermissions(
            collect($deputyPermissions)->map(fn ($key) => ['key' => $key])->toArray()
        );

        // ۴. مدیرعامل
        // مسئول تایید نهایی اضافه‌کار
        $ceoRole = Role::updateOrCreate(
            ['name' => 'مدیرعامل'],
            ['desk' => 'تایید نهایی اضافه‌کار برای تمام سازمان', 'hidden' => false, 'is_default' => false]
        );

        $ceoPermissions = [
            'overtime/ceo-approve',      // تایید نهایی اضافه‌کار
            'menu/dashboard',
            'menu/overtime-entry',
            'menu/personnel',
            'menu/deputies',
            'menu/positions',
            'menu/units',
            'menu/contract-types',
            'menu/reports',
        ];

        $ceoRole->syncPermissions(
            collect($ceoPermissions)->map(fn ($key) => ['key' => $key])->toArray()
        );

        // خروجی
        if ($this->command) {
            $this->command->info('✅ نقش‌های سامانه مدیریت اضافه‌کار ایجاد شدند:');
            $this->command->info('  - مدیر منابع انسانی (HR)');
            $this->command->info('  - مدیر واحد');
            $this->command->info('  - معاون');
            $this->command->info('  - مدیرعامل');
        }
    }
}
