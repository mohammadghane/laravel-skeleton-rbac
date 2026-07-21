<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use Auditable;

    protected $fillable = ['deputy_id', 'title', 'manager_user_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function auditLabel(): string
    {
        return __('واحد') . ': ' . $this->title;
    }

    public function deputy(): BelongsTo
    {
        return $this->belongsTo(Deputy::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function personnel(): HasMany
    {
        return $this->hasMany(User::class, 'unit_id');
    }
}
