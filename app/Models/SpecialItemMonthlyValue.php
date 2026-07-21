<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Support\Jalali;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpecialItemMonthlyValue extends Model
{
    use Auditable;

    protected $fillable = ['special_item_id', 'personnel_id', 'jalali_year', 'jalali_month', 'value', 'entered_by'];

    protected $casts = ['value' => 'decimal:2'];

    public function auditLabel(): string
    {
        return __('مقدار آیتم ویژه') . ': ' . $this->specialItem?->title . ' - '
            . $this->personnel?->name . ' - ' . Jalali::periodLabel($this->jalali_year, $this->jalali_month);
    }

    public function specialItem(): BelongsTo
    {
        return $this->belongsTo(SpecialItem::class);
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'personnel_id');
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }
}
