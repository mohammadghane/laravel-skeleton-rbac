<?php

namespace App\Http\Requests\Admin\Overtime;

use Illuminate\Foundation\Http\FormRequest;

class UnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('overtime/org-chart-manage');
    }

    public function rules(): array
    {
        return [
            'deputy_id' => ['required', 'integer', 'exists:deputies,id'],
            'title' => ['required', 'string', 'max:190'],
            'manager_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'is_active' => ['boolean'],
        ];
    }
}
