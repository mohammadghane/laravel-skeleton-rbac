<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route-level `permission` middleware already gates access
    }

    public function rules(): array
    {
        $userId = $this->input('id');

        return [
            // بدون این قانون، `id` وارد validated() نمی‌شد و UserController::store()
            // همیشه updateOrCreate(['id' => null]) صدا می‌زد - یعنی ویرایش هر کاربر
            // به‌جای به‌روزرسانی، یک رکورد تکراری می‌ساخت.
            'id' => ['nullable', 'integer', 'exists:users,id'],
            'name' => ['required', 'string', 'max:255'],
            'family' => ['nullable', 'string', 'max:255'],
            'gender' => ['required', 'in:1,2'], // GENDER_MALE / GENDER_FEMALE constants -> replace with an enum
            'username' => ['required', 'string', 'max:255', Rule::unique('users')->ignore($userId)],
            // فرمت استاندارد شماره موبایل: ۱۱ رقم با صفر ابتدایی (مثل 09121111111).
            // ورودی در prepareForValidation نرمال‌سازی می‌شود، بنابراین کاربر می‌تواند
            // 9121111111 یا +989121111111 یا 989121111111 هم وارد کند و همه به شکل
            // یکسانِ 09121111111 ذخیره می‌شوند.
            'cell' => ['required', 'regex:/^09\d{9}$/', Rule::unique('users')->ignore($userId)],
            'tell' => ['nullable', 'regex:/^0\d{2,3}\d{7,8}$/'],
            'national_code' => ['nullable', 'digits:10', Rule::unique('users')->ignore($userId)],
            'email' => ['nullable', 'email', Rule::unique('users')->ignore($userId)],
            // اختیاری هستند (طبق درخواست کارفرما)؛ پیش از این required بودند در حالی که
            // اصلاً در فرم وجود نداشتند و ثبت هر کاربر را غیرممکن می‌کردند.
            'province' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'zip_code' => ['nullable', 'digits:10'],
            'addr' => ['nullable', 'string', 'max:500'],
            'password' => ['nullable', 'string', 'min:8'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['integer', 'exists:roles,id'],

            // فیلدهای چارت سازمانی - لازمِ ماژول اضافه‌کار (بند ۴.۱ شرح خدمات).
            // پرسنل یا زیرمجموعه‌ی یک واحد است یا مستقیماً زیرمجموعه‌ی یک معاونت.
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'deputy_id' => ['nullable', 'integer', 'exists:deputies,id'],
            'position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'contract_type_id' => ['nullable', 'integer', 'exists:contract_types,id'],
        ];
    }

    /**
     * نرمال‌سازی شماره موبایل پیش از اعتبارسنجی: ارقام فارسی/عربی به لاتین تبدیل می‌شوند
     * و پیش‌شماره‌های +98 / 98 / بدون صفر همگی به شکل استاندارد 09xxxxxxxxx درمی‌آیند.
     * این‌طور کاربر مجبور نیست فرمت دقیق را حدس بزند و داده‌ی ذخیره‌شده هم یکدست می‌ماند.
     */
    protected function prepareForValidation(): void
    {
        if (!$this->filled('cell')) {
            return;
        }

        $this->merge(['cell' => self::normalizeCell((string) $this->input('cell'))]);
    }

    /** شماره‌ی موبایل را به شکل استاندارد 09xxxxxxxxx برمی‌گرداند. */
    public static function normalizeCell(string $cell): string
    {
        // ارقام فارسی (۰۱۲…) و عربی (٠١٢…) به لاتین
        $cell = strtr($cell, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        // حذف فاصله، خط تیره و پرانتز
        $digits = preg_replace('/\D+/', '', $cell) ?? '';

        // 989121111111 یا +989121111111 => 9121111111
        if (str_starts_with($digits, '98') && strlen($digits) === 12) {
            $digits = substr($digits, 2);
        }

        // 9121111111 => 09121111111
        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            $digits = '0' . $digits;
        }

        return $digits;
    }

    public function messages(): array
    {
        return [
            'username.unique' => __('نام کاربری وارد شده تکراری می باشد.'),
            'email.email' => __('فرمت ایمیل وارد شده اشتباه می باشد.'),
            'email.unique' => __('ایمیل وارد شده تکراری می باشد.'),
            'cell.regex' => __('شماره موبایل باید ۱۱ رقم و با ۰۹ شروع شود (مثال: ۰۹۱۲۱۱۱۱۱۱۱).'),
            'cell.unique' => __('شماره همراه وارد شده تکراری می باشد.'),
            'tell.regex' => __('فرمت شماره تلفن وارد شده اشتباه می باشد.'),
            'national_code.digits' => __('کد ملی وارد شده اشتباه می باشد.'),
            'national_code.unique' => __('کد ملی وارد شده تکراری می باشد.'),
        ];
    }
}
