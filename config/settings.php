<?php

/**
 * Replaces the old application/config/settings.php (not included in the uploaded zip, so this
 * is a representative template covering every field type the old form.php view supported -
 * replace with your actual settings list, same shape as before.
 *
 * Group keys:
 *   title       - tab label
 *   type        - free-form label (e.g. 'general', 'shop'). Not filtered on by default -
 *                 every group shows up in SettingsController::edit(). If you want a
 *                 "quick settings" vs "full settings" split like the old settings() vs
 *                 centerSettings() actions, filter on it yourself in resolveGroups().
 *
 * Item keys:
 *   title           - field label
 *   type            - text | textarea | checkbox | select | from_to
 *   desk            - help text under the field
 *   validation      - passed to a FormRequest/validator if you add one (not enforced here yet)
 *   has_pattern     - true = scoped per center/user, false = single global row
 *   privilage       - permission key required to see/edit this field (checked via @permission)
 *   default_value   - fallback shown when no row exists yet
 *   options         - for `select`: either a plain ['value'=>..,'text'=>..][] array, OR a
 *                      closure resolved at render time (replaces the old
 *                      options['#type']=='function' + provider indirection - see
 *                      SettingsController::resolveOptions())
 */

return [
    'general' => [
        'title' => 'عمومی',
        'type' => 'general',
        'items' => [
            'site_title' => [
                'title' => 'عنوان سایت',
                'type' => 'text',
                'has_pattern' => true,
                'default_value' => '',
            ],
            'site_description' => [
                'title' => 'توضیحات سایت',
                'type' => 'textarea',
                'has_pattern' => true,
                'default_value' => '',
            ],
            'maintenance_mode' => [
                'title' => 'حالت تعمیر و نگهداری',
                'type' => 'checkbox',
                'desk' => 'در صورت فعال بودن، سایت برای کاربران عادی در دسترس نخواهد بود',
                'has_pattern' => true,
                'default_value' => false,
                'privilage' => 'system-manager',
            ],
        ],
    ],
    'shipping' => [
        'title' => 'ارسال و پست',
        'type' => 'shop',
        'items' => [
            'post_cost_type' => [
                'title' => 'روش محاسبه هزینه ارسال',
                'type' => 'select',
                'has_pattern' => true,
                'default_value' => '',
                'options' => fn () => [
                    ['value' => 'fixed', 'text' => 'هزینه ثابت'],
                    ['value' => 'weight', 'text' => 'هزینه متغیر بر اساس وزن سبد خرید'],
                    ...(auth()->user()?->hasPermission('admin/post-cost')
                        ? [['value' => 'cart_price', 'text' => 'هزینه متغیر بر اساس مبلغ کل سبد خرید و موقعیت جغرافیایی']]
                        : []),
                ],
            ],
            'business_hours' => [
                'title' => 'ساعات کاری',
                'type' => 'from_to',
                'has_pattern' => true,
                'default_value' => ['from' => '', 'to' => ''],
            ],
        ],
    ],
];
