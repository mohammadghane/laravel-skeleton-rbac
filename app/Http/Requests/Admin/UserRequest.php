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
            'name' => ['required', 'string', 'max:255'],
            'family' => ['nullable', 'string', 'max:255'],
            'gender' => ['required', 'in:1,2'], // GENDER_MALE / GENDER_FEMALE constants -> replace with an enum
            'username' => ['required', 'string', 'max:255', Rule::unique('users')->ignore($userId)],
            // NOTE: old CellFilter::filter() normalized the leading 0/98/+98 before saving -
            // replicate that normalization in the controller before this rule runs, or in a
            // custom cast on the User model, so this regex matches the *stored* format.
            'cell' => ['required', 'regex:/^9\d{9}$/', Rule::unique('users')->ignore($userId)],
            'tell' => ['nullable', 'regex:/^0\d{2,3}\d{7,8}$/'],
            'national_code' => ['nullable', 'digits:10', Rule::unique('users')->ignore($userId)],
            'email' => ['nullable', 'email', Rule::unique('users')->ignore($userId)],
            'province' => ['required'],
            'city' => ['required'],
            'zip_code' => ['nullable', 'digits:10'],
            'addr' => ['nullable', 'string', 'max:500'],
            'password' => ['nullable', 'string', 'min:8'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.unique' => __('نام کاربری وارد شده تکراری می باشد.'),
            'email.email' => __('فرمت ایمیل وارد شده اشتباه می باشد.'),
            'email.unique' => __('ایمیل وارد شده تکراری می باشد.'),
            'cell.regex' => __('فرمت شماره موبایل وارد شده اشتباه می باشد.'),
            'cell.unique' => __('شماره همراه وارد شده تکراری می باشد.'),
            'tell.regex' => __('فرمت شماره تلفن وارد شده اشتباه می باشد.'),
            'national_code.digits' => __('کد ملی وارد شده اشتباه می باشد.'),
            'national_code.unique' => __('کد ملی وارد شده تکراری می باشد.'),
        ];
    }
}
