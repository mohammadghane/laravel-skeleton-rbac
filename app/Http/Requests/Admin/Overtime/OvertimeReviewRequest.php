<?php

namespace App\Http\Requests\Admin\Overtime;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** تایید/رد یک دوره‌ی اضافه‌کار توسط معاونت یا مدیرعامل. */
class OvertimeReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // مالکیت/نقش داخل کنترلر چک می‌شود
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'note' => ['nullable', 'string', 'max:1000', 'required_if:decision,reject'],
            // فقط برای deputyReview کاربرد دارد؛ در ceoReview نادیده گرفته می‌شود
            'excess_hours' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
