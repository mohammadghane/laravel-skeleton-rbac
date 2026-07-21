<?php

namespace App\Http\Requests\Admin\Overtime;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('overtime/org-chart-manage');
    }

    public function rules(): array
    {
        $id = $this->route('position')?->id;

        return [
            'title' => ['required', 'string', 'max:190', Rule::unique('positions', 'title')->ignore($id)],
            'is_active' => ['boolean'],
        ];
    }
}
