<?php

namespace App\Http\Requests\Admin\Overtime;

use App\Support\Jalali;
use Illuminate\Foundation\Http\FormRequest;

/** ثبت/ویرایش اضافه‌کار عادی یک پرسنل توسط مدیر واحد. */
class OvertimeNormalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // مالکیت (آیا این مدیر، مدیرِ همین پرسنل است) داخل کنترلر چک می‌شود
    }

    public function rules(): array
    {
        return [
            'normal_hours' => ['required', 'numeric', 'min:0'],
            'jalali_year' => ['required', 'integer', 'min:1390', 'max:1450'],
            'jalali_month' => ['required', 'integer', 'min:1', 'max:12'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!$this->filled('jalali_year') || !$this->filled('jalali_month')) {
            $this->merge([
                'jalali_year' => $this->input('jalali_year', Jalali::currentYear()),
                'jalali_month' => $this->input('jalali_month', Jalali::currentMonth()),
            ]);
        }
    }
}
