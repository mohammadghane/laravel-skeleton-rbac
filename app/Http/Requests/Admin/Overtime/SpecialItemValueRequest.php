<?php

namespace App\Http\Requests\Admin\Overtime;

use App\Support\Jalali;
use Illuminate\Foundation\Http\FormRequest;

class SpecialItemValueRequest extends FormRequest
{
    public function authorize(): bool
    {
        // مقدار ماهانه‌ی آیتم فقط توسط HR ثبت می‌شود (نگاه کنید به SpecialItemValueController)
        return $this->user()->hasPermission('overtime/hr-manage');
    }

    public function rules(): array
    {
        return [
            'value' => ['required', 'integer', 'min:0'],
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
