<?php

/**
 * Replaces the old application/config/permisions.php (not included in the uploaded zip,
 * so only the module keys actually referenced elsewhere in the code are stubbed here —
 * fill this in with your full list from the CI project, same shape as before).
 *
 * Used by RoleController::permissions() to render the tabbed permission-assignment form,
 * and by database/seeders/PermissionSeeder.php to seed the `permissions` table.
 */

return [
    'shop' => [
        'title' => 'فروشگاه',
        'perms' => [
            'general' => [
                'title' => 'دسترسی های عمومی',
                'items' => [
                    'shop-manager' => ['title' => 'مدیر فروشگاه'],
                    'admin/center/profile' => ['title' => 'پروفایل مرکز'],
                    'admin/categories' => ['title' => 'دسته بندی ها'],
                    'admin/products' => ['title' => 'محصولات'],
                    'admin/orders' => ['title' => 'سفارشات'],
                    'admin/settings' => ['title' => 'تنظیمات مرکز (سایر مراکز)'],
                ],
            ],
        ],
    ],
    'users' => [
        'title' => 'کاربران',
        'perms' => [
            'general' => [
                'title' => 'دسترسی های عمومی',
                'items' => [
                    'admin/users' => ['title' => 'مدیریت کاربران'],
                    'admin/roles' => ['title' => 'مدیریت نقش ها'],
                    'admin/audit-logs' => ['title' => 'گزارش تغییرات (Audit Log)'],
                    'system-manager' => ['title' => 'مدیر کل سیستم'],
                ],
            ],
        ],
    ],
    'overtime' => [
        'title' => 'اضافه‌کار پرسنل',
        'perms' => [
            'general' => [
                'title' => 'دسترسی‌های عمومی',
                'items' => [
                    'overtime/hr-manage' => ['title' => 'مدیریت پارامترهای اضافه‌کار (منابع انسانی)'],
                    'overtime/ceo-approve' => ['title' => 'تایید نهایی اضافه‌کار (مدیرعامل)'],
                    'overtime/org-chart-manage' => ['title' => 'مدیریت چارت سازمانی (واحد/معاونت/سمت/قرارداد)'],
                    'overtime/reports' => ['title' => 'مشاهده گزارش‌های اضافه‌کار'],
                ],
            ],
        ],
    ],
];
