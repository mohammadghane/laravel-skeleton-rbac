<?php

namespace App\Http\Requests\Admin\Overtime;

use App\Support\Jalali;
use Illuminate\Foundation\Http\FormRequest;

/**
 * ویرایش گروهی سقف چند نوع قرارداد به‌صورت هم‌زمان.
 *
 * همه‌ی فیلدهای مقدار (سقف هر نفر / سقف کلی / روز فعال‌سازی) اختیاری‌اند: کاربر فقط
 * موردی را که می‌خواهد تغییر دهد پر می‌کند و بقیه دست‌نخورده باقی می‌مانند (مقدار مؤثر
 * فعلی‌شان حفظ می‌شود، نه اینکه پاک شوند). به همین دلیل `required` روی هیچ‌کدام نیست و
 * به‌جای آن در withValidator بررسی می‌شود که دست‌کم یک فیلد پر شده باشد.
 */
class ContractTypeCeilingBulkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('overtime/hr-manage');
    }

    public function rules(): array
    {
        return [
            'contract_type_ids' => ['required', 'array', 'min:1'],
            'contract_type_ids.*' => ['integer', 'exists:contract_types,id'],

            'jalali_year' => ['required', 'integer', 'min:1390', 'max:1450'],
            'jalali_month' => ['required', 'integer', 'min:1', 'max:12'],

            'ceiling_per_person' => ['nullable', 'integer', 'min:0'],
            'overall_ceiling' => ['nullable', 'integer', 'min:0'],
            'activation_day' => ['nullable', 'integer', 'min:1', 'max:31'],

            // اگر تیک بخورد، مقدار خالیِ «سقف کلی» به‌معنای پاک کردن سقف است، نه
            // «تغییر نده». بدون این پرچم نمی‌شد سقف کلی را عمداً برداشت.
            'clear_overall_ceiling' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $hasAnyValue = $this->filled('ceiling_per_person')
                || $this->filled('overall_ceiling')
                || $this->filled('activation_day')
                || $this->boolean('clear_overall_ceiling');

            if (!$hasAnyValue) {
                $validator->errors()->add('overall_ceiling', __('دست‌کم یکی از مقادیر را برای ویرایش گروهی وارد کنید.'));
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if (!$this->filled('jalali_year') || !$this->filled('jalali_month')) {
            $this->merge([
                'jalali_year' => $this->input('jalali_year', Jalali::currentYear()),
                'jalali_month' => $this->input('jalali_month', Jalali::currentMonth()),
            ]);
        }
    }
}
