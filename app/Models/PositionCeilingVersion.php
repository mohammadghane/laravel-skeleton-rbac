<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Support\Jalali;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PositionCeilingVersion extends Model
{
    use Auditable;

    /**
     * دو سقف مجزا روی همین رکورد:
     *   - ceiling_per_person         => سقف «مازاد»، در اختیار معاونت
     *   - manager_ceiling_per_person => سقف «عادی»، در اختیار مدیر واحد
     */
    protected $fillable = [
        'position_id', 'jalali_year', 'jalali_month',
        'ceiling_per_person', 'manager_ceiling_per_person', 'created_by',
    ];

    protected $casts = [
        'ceiling_per_person' => 'decimal:2',
        'manager_ceiling_per_person' => 'decimal:2',
    ];

    public function auditLabel(): string
    {
        return __('سقف سمت') . ': ' . $this->position?->title . ' - ' . Jalali::periodLabel($this->jalali_year, $this->jalali_month);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
