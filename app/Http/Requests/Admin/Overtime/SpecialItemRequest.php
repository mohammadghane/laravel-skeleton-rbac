<?php

namespace App\Http\Requests\Admin\Overtime;

use Illuminate\Foundation\Http\FormRequest;

class SpecialItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('overtime/hr-manage');
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:190'],
            'unit' => ['required', 'in:hour,day'],
            'default_value' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }
}
