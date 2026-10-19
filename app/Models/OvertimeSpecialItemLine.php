<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvertimeSpecialItemLine extends Model
{
    protected $fillable = [
        'overtime_period_id', 'special_item_id', 'title_snap', 'hours',
        'unit_snap', 'requires_deputy_approval', 'deputy_approved', 'entered_by',
    ];

    protected $casts = [
        'hours' => 'integer',
        'requires_deputy_approval' => 'boolean',
        'deputy_approved' => 'boolean',
    ];

    public function overtimePeriod(): BelongsTo
    {
        return $this->belongsTo(OvertimePeriod::class);
    }

    public function specialItem(): BelongsTo
    {
        return $this->belongsTo(SpecialItem::class);
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }
}
