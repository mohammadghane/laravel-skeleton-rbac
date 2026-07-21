<?php

namespace App\Http\Requests\Admin\Overtime;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContractTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('overtime/org-chart-manage');
    }

    public function rules(): array
    {
        $id = $this->route('contract_type')?->id;

        return [
            'title' => ['required', 'string', 'max:190', Rule::unique('contract_types', 'title')->ignore($id)],
            'is_active' => ['boolean'],
        ];
    }
}
