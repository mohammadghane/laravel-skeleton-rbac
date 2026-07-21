<?php

namespace App\Http\Requests\Admin\Overtime;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'is_variable_per_personnel' => ['boolean'],
            'default_value' => ['required', 'numeric', 'min:0'],
            'submitter_role' => ['required', Rule::in(['hr', 'unit_manager'])],
            'is_active' => ['boolean'],
        ];
    }
}
