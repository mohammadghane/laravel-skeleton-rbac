<?php

/**
 * فهرست دسترسی‌های سیستم - مبنای دو چیز:
 *   ۱) فرم تخصیص دسترسی به نقش‌ها (RoleController::permissions())
 *   ۲) جدول `permissions` از طریق database/seeders/PermissionSeeder.php
 *
 * ساختار: هر **منو** دسترسی مستقل خودش را دارد، تا نقش‌ها را بتوان دقیق تعریف کرد
 * (مثلاً نقشی که فقط «سمت سازمانی» را ببیند، بدون دسترسی به «معاونت» یا «واحد»).
 *
 * کلیدهای منو با پیشوند `menu/` مشخص شده‌اند تا از دسترسی‌های «عملیاتی» (مثل
 * overtime/ceo-approve که تعیین می‌کند چه کسی تایید نهایی می‌کند) جدا بمانند.
 *
 * توجه: دسترسی `system-manager` همه‌ی بررسی‌ها را دور می‌زند - نگاه کنید به
 * App\Concerns\HasRoles::hasPermission().
 */

return [
    'menus' => [
        'title' => 'دسترسی به منوها',
        'perms' => [
            'general' => [
                'title' => 'منوهای سامانه',
                'items' => [
                    'menu/dashboard' => ['title' => 'داشبورد و گزارش‌ها'],
                    'menu/personnel' => ['title' => 'پرسنل'],
                    'menu/deputies' => ['title' => 'معاونت'],
                    'menu/positions' => ['title' => 'سمت سازمانی'],
                    'menu/units' => ['title' => 'واحد سازمانی'],
                    'menu/contract-types' => ['title' => 'قراردادها و سقف‌ها'],
                    'menu/personnel-sync' => ['title' => 'همگام‌سازی پرسنل'],
                    'menu/import' => ['title' => 'کسر کار / ماموریت'],
                    'menu/special-items' => ['title' => 'آیتم‌های ویژه'],
                    'menu/capacity-transfers' => ['title' => 'واگذاری ظرفیت مازاد'],
                    'menu/overtime-entry' => ['title' => 'ثبت اضافه‌کار'],
                    'menu/roles' => ['title' => 'نقش سازمانی'],
                    'menu/audit-logs' => ['title' => 'گزارش تغییرات'],
                ],
            ],
        ],
    ],

    'overtime' => [
        'title' => 'عملیات اضافه‌کار',
        'perms' => [
            'general' => [
                'title' => 'دسترسی‌های عملیاتی',
                'items' => [
                    // این‌ها «منو» نیستند؛ تعیین می‌کنند کاربر در گردش کار چه نقشی دارد
                    // و چه محدوده‌ای از داده را می‌بیند.
                    'overtime/hr-manage' => ['title' => 'مدیریت پارامترهای اضافه‌کار (منابع انسانی)'],
                    'overtime/ceo-approve' => ['title' => 'تایید نهایی اضافه‌کار (مدیرعامل)'],
                    'overtime/org-chart-manage' => ['title' => 'ویرایش چارت سازمانی (واحد/معاونت/سمت/قرارداد)'],
                    'overtime/reports' => ['title' => 'مشاهده گزارش‌های همه‌ی واحدها'],
                ],
            ],
        ],
    ],

    'system' => [
        'title' => 'مدیریت سیستم',
        'perms' => [
            'general' => [
                'title' => 'دسترسی‌های عمومی',
                'items' => [
                    'admin/users' => ['title' => 'ویرایش و حذف پرسنل'],
                    'admin/roles' => ['title' => 'ویرایش نقش‌ها و دسترسی‌ها'],
                    'admin/audit-logs' => ['title' => 'مشاهده‌ی گزارش تغییرات'],
                    'system-manager' => ['title' => 'مدیر کل سیستم (دسترسی کامل)'],
                ],
            ],
        ],
    ],
];
