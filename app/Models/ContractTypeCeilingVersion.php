<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Support\Jalali;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractTypeCeilingVersion extends Model
{
    use Auditable;

    protected $fillable = [
        'contract_type_id', 'jalali_year', 'jalali_month',
        'ceiling_per_person', 'overall_ceiling', 'activation_day', 'created_by',
    ];

    protected $casts = [
        'ceiling_per_person' => 'integer',
        'overall_ceiling' => 'integer',
    ];

    public function auditLabel(): string
    {
        return __('سقف قرارداد') . ': ' . $this->contractType?->title . ' - ' . Jalali::periodLabel($this->jalali_year, $this->jalali_month);
    }

    public function contractType(): BelongsTo
    {
        return $this->belongsTo(ContractType::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isRegistrationWindowOpen(int $jalaliYear, int $jalaliMonth): bool
    {
        return Jalali::isWithinActivationWindow($jalaliYear, $jalaliMonth, $this->activation_day);
    }
}
