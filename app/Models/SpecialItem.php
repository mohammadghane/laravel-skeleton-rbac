<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SpecialItem extends Model
{
    use Auditable;

    public const SUBMITTER_HR = 'hr';
    public const SUBMITTER_UNIT_MANAGER = 'unit_manager';

    protected $fillable = [
        'title', 'is_variable_per_personnel', 'default_value', 'submitter_role', 'is_active',
    ];

    protected $casts = [
        'is_variable_per_personnel' => 'boolean',
        'is_active' => 'boolean',
        'default_value' => 'decimal:2',
    ];

    public function auditLabel(): string
    {
        return __('آیتم ویژه') . ': ' . $this->title;
    }

    public function eligiblePersonnel(): HasMany
    {
        return $this->hasMany(PersonnelSpecialItem::class);
    }

    public function monthlyValues(): HasMany
    {
        return $this->hasMany(SpecialItemMonthlyValue::class);
    }

    /** آیا ثبت این آیتم نیازمند تایید معاونت در گردش کار اضافه‌کار است؟ */
    public function requiresDeputyApproval(): bool
    {
        return $this->submitter_role === self::SUBMITTER_UNIT_MANAGER;
    }
}
