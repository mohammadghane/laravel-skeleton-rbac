<?php

namespace App\Http\Requests\Admin\Overtime;

use Illuminate\Foundation\Http\FormRequest;

/** ثبت/ویرایش اضافه‌کار مازاد یک پرسنل توسط معاونت (بخشی از deputyReview). */
class OvertimeExcessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // مالکیت داخل کنترلر چک می‌شود
    }

    public function rules(): array
    {
        return [
            'excess_hours' => ['required', 'numeric', 'min:0'],
        ];
    }
}
