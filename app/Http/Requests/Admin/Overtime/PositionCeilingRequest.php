<?php

namespace App\Http\Requests\Admin\Overtime;

use App\Support\Jalali;
use Illuminate\Foundation\Http\FormRequest;

class PositionCeilingRequest extends FormRequest
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
            'ceiling_per_person' => ['required', 'integer', 'min:0'],
            // سقف «عادی» در اختیار مدیر واحد. اختیاری است تا سمت‌هایی که
            // فقط سقف مازاد دارند هم قابل ثبت بمانند.
            'manager_ceiling_per_person' => ['nullable', 'integer', 'min:0'],
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
