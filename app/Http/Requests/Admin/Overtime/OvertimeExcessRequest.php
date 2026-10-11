<?php

namespace App\Http\Requests\Admin\Overtime;

use Illuminate\Foundation\Http\FormRequest;

/** ثبت/ویرایش مستقل اضافه‌کار مازاد توسط معاونت. */
class OvertimeExcessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // مالکیت معاونت و وضعیت دوره در کنترلر بررسی می‌شود.
    }

    public function rules(): array
    {
        return [
            'excess_hours' => ['required', 'numeric', 'min:0'],
        ];
    }
}
