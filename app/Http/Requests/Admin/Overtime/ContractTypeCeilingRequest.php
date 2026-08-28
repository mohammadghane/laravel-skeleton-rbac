<?php

namespace App\Http\Requests\Admin\Overtime;

use App\Support\Jalali;
use Illuminate\Foundation\Http\FormRequest;

class ContractTypeCeilingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('overtime/hr-manage');
    }

    public function rules(): array
    {
        return [
            'jalali_year' => ['required', 'integer', 'min:1390', 'max:1450'],
            'jalali_month' => ['required', 'integer', 'min:1', 'max:12'],
            // این ستون پس از بازنگری بند ۴.۲ دیگر توسط موتور سقف استفاده نمی‌شود («سقف در
            // اختیار مدیر واحد» به سمت سازمانی منتقل شد)، ولی ستون NOT NULL است؛ پس
            // اختیاری می‌ماند و در کنترلر با صفر پر می‌شود.
            'ceiling_per_person' => ['nullable', 'numeric', 'min:0'],
            // سقف کلی قرارداد (بند ۴.۲) - اختیاری؛ اگر تعریف نشود هیچ capping ای در خروجی
            // مالی اعمال نمی‌شود.
            'overall_ceiling' => ['nullable', 'numeric', 'min:0'],
            'activation_day' => ['required', 'integer', 'min:1', 'max:31'],
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
