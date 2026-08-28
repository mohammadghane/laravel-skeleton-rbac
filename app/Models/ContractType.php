<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractType extends Model
{
    use Auditable;

    protected $fillable = ['title', 'external_code', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function auditLabel(): string
    {
        return __('نوع قرارداد') . ': ' . $this->title;
    }

    public function ceilingVersions(): HasMany
    {
        return $this->hasMany(ContractTypeCeilingVersion::class);
    }

    public function personnel(): HasMany
    {
        return $this->hasMany(User::class, 'contract_type_id');
    }

    /** سقف عادی به ازای هر نفر برای یک ماه شمسی مشخص - با fallback به آخرین نسخه‌ی ثبت‌شده‌ی قبل از آن ماه. */
    public function ceilingFor(int $jalaliYear, int $jalaliMonth): ?ContractTypeCeilingVersion
    {
        return $this->ceilingVersions()
            ->where(function ($q) use ($jalaliYear, $jalaliMonth) {
                $q->where('jalali_year', '<', $jalaliYear)
                    ->orWhere(function ($q2) use ($jalaliYear, $jalaliMonth) {
                        $q2->where('jalali_year', $jalaliYear)->where('jalali_month', '<=', $jalaliMonth);
                    });
            })
            ->orderByDesc('jalali_year')
            ->orderByDesc('jalali_month')
            ->first();
    }
}
