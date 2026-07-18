<?php

return [
    /*
     * code => display label, shown in the locale switcher.
     * Add a locale here and it shows up everywhere automatically - the switcher,
     * SetLocale middleware's allow-list, and the /locale/{locale} route's validation.
     */
    'available' => [
        'fa' => 'فارسی',
        'en' => 'English',
    ],

    // Locales that render dir="rtl". Anything not listed here renders "ltr".
    'rtl' => ['fa', 'ar', 'he', 'ur'],
];
