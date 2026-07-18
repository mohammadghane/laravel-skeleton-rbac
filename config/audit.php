<?php

/**
 * Display labels for the audit log viewer's "model type" column/filter.
 * class_basename() alone gives English names (User, Role, ...), which don't fit this app's
 * translation convention of using the Persian text itself as the __() key - so every
 * Auditable model gets an explicit Persian label here instead. Falls back to the raw
 * class basename if a model isn't listed, so nothing breaks if you forget to add one -
 * it just won't be translated.
 */

return [
    'model_labels' => [
        \App\Models\User::class => 'کاربر',
        \App\Models\Role::class => 'نقش',
        \App\Models\Permission::class => 'مجوز',
        \App\Models\Setting::class => 'تنظیم',
    ],
];
