<?php

namespace App\Http\Requests\Admin\Overtime;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeputyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('overtime/org-chart-manage');
    }

    public function rules(): array
    {
        $deputyId = $this->route('deputy')?->id;

        return [
            'title' => ['required', 'string', 'max:190', Rule::unique('deputies', 'title')->ignore($deputyId)],
            'head_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'is_active' => ['boolean'],
        ];
    }
}
