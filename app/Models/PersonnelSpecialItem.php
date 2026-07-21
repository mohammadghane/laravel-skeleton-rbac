<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonnelSpecialItem extends Model
{
    use Auditable;

    protected $fillable = ['personnel_id', 'special_item_id', 'is_eligible', 'assigned_by'];

    protected $casts = ['is_eligible' => 'boolean'];

    public function auditLabel(): string
    {
        return __('مشمولیت آیتم ویژه') . ': ' . $this->specialItem?->title . ' - ' . $this->personnel?->name;
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'personnel_id');
    }

    public function specialItem(): BelongsTo
    {
        return $this->belongsTo(SpecialItem::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
