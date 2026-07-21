<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Deputy extends Model
{
    use Auditable;

    protected $fillable = ['title', 'head_user_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function auditLabel(): string
    {
        return __('معاونت') . ': ' . $this->title;
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    /** پرسنلی که بی‌واسطه (بدون واحد میانی) زیرمجموعه‌ی این معاونت هستند. */
    public function directPersonnel(): HasMany
    {
        return $this->hasMany(User::class, 'deputy_id');
    }

    /** همه‌ی پرسنل زیرمجموعه‌ی این معاونت، هم مستقیم و هم از طریق واحدها. */
    public function allPersonnelIds(): \Illuminate\Support\Collection
    {
        $unitIds = $this->units()->pluck('id');

        return User::query()
            ->where('deputy_id', $this->id)
            ->orWhereIn('unit_id', $unitIds)
            ->pluck('id');
    }
}
