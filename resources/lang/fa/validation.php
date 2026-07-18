<?php

// Persian translation of Laravel's default validation messages. Only the rules actually used
// by this app's FormRequests are filled in - extend as you add more rules elsewhere.
// Laravel falls back to lang/en/validation.php (shipped with the framework) for anything
// not overridden here, so nothing breaks if a rule is missing from this file - it'll just
// show up in English until you add it.

return [
    'required' => ':attribute الزامی است.',
    'string' => ':attribute باید متنی باشد.',
    'max' => [
        'string' => ':attribute نباید بیشتر از :max کاراکتر باشد.',
    ],
    'min' => [
        'string' => ':attribute باید حداقل :min کاراکتر باشد.',
    ],
    'email' => 'فرمت :attribute معتبر نیست.',
    'unique' => ':attribute قبلاً استفاده شده است.',
    'exists' => ':attribute انتخاب شده معتبر نیست.',
    'digits' => ':attribute باید :digits رقم باشد.',
    'regex' => 'فرمت :attribute معتبر نیست.',
    'in' => ':attribute انتخاب شده معتبر نیست.',
    'array' => ':attribute باید آرایه باشد.',
    'integer' => ':attribute باید عدد صحیح باشد.',
    'nullable' => null,
    'confirmed' => 'تایید :attribute مطابقت ندارد.',

    'attributes' => [
        'name' => 'نام',
        'family' => 'نام خانوادگی',
        'gender' => 'جنسیت',
        'username' => 'نام کاربری',
        'cell' => 'شماره موبایل',
        'tell' => 'تلفن ثابت',
        'national_code' => 'کد ملی',
        'email' => 'ایمیل',
        'province' => 'استان',
        'city' => 'شهر',
        'zip_code' => 'کد پستی',
        'addr' => 'آدرس',
        'password' => 'کلمه عبور',
        'desk' => 'توضیحات',
        'roles' => 'نقش‌ها',
        'permissions' => 'دسترسی‌ها',
        'key' => 'کلید',
        'value' => 'مقدار',
        'module' => 'ماژول',
        'title' => 'عنوان',
        'center_id' => 'مرکز',
        'agency_id' => 'نمایندگی',
        'is_agency' => 'نمایندگی است',
        'is_provider' => 'تامین‌کننده است',
        'sana_code' => 'ساناکد',
    ],
];
