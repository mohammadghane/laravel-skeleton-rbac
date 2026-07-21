<?php

namespace App\Http\Requests\Admin\Overtime;

use Illuminate\Foundation\Http\FormRequest;

/** افزودن یک خط آیتم ویژه به دوره‌ی اضافه‌کار یک پرسنل. */
class OvertimeSpecialItemLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // بر اساس special_items.submitter_role در کنترلر چک می‌شود
    }

    public function rules(): array
    {
        return [
            'jalali_year' => ['required', 'integer', 'min:1390', 'max:1500'],
            'jalali_month' => ['required', 'integer', 'min:1', 'max:12'],
            'hours' => ['required', 'numeric', 'min:0'],
        ];
    }
}
