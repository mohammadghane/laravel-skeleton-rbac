<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class LocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('menu/locations');
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:190'],
            // محدوده‌ی معتبر مختصات جغرافیایی. بدون این، یک کلیک اشتباه یا داده‌ی
            // دستکاری‌شده نقطه‌ای بیرون از نقشه ذخیره می‌کرد.
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            // شعاع مجاز بر حسب متر. حداقل ۱ متر: شعاع صفر یعنی هیچ نقطه‌ای
            // هرگز مجاز نیست که عملاً موقعیت را بی‌استفاده می‌کند.
            'allowed_radius' => ['required', 'integer', 'min:1', 'max:100000'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => __('عنوان موقعیت الزامی است.'),
            'latitude.required' => __('نقطه‌ای روی نقشه انتخاب نشده است.'),
            'longitude.required' => __('نقطه‌ای روی نقشه انتخاب نشده است.'),
            'latitude.between' => __('عرض جغرافیایی باید بین ۹۰- و ۹۰ باشد.'),
            'longitude.between' => __('طول جغرافیایی باید بین ۱۸۰- و ۱۸۰ باشد.'),
            'allowed_radius.required' => __('شعاع مجاز الزامی است.'),
            'allowed_radius.min' => __('شعاع مجاز باید دست‌کم ۱ متر باشد.'),
        ];
    }

    protected function prepareForValidation(): void
    {
        // چک‌باکس غیرفعال اصلاً ارسال نمی‌شود؛ بدون این، ویرایش یک موقعیتِ فعال
        // هیچ‌وقت آن را غیرفعال نمی‌کرد.
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }
}
