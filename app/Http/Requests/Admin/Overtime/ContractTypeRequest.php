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
        // نام پارامتر باید دقیقاً با مسیر یکی باشد: routes/overtime.php از
        // {contractType} استفاده می‌کند. با نام اشتباه، route() بی‌سروصدا null
        // برمی‌گرداند، ignore بی‌اثر می‌شود و رکورد با خودش تداخل پیدا می‌کند -
        // یعنی ذخیره‌ی قرارداد با عنوانِ بدون تغییر، خطای «تکراری» می‌داد.
        $id = $this->route('contractType')?->id;

        return [
            'title' => ['required', 'string', 'max:190', Rule::unique('contract_types', 'title')->ignore($id)],
            'is_active' => ['boolean'],
        ];
    }
}
