<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvertimeStatusHistory extends Model
{
    // «history» جمعِ خودش است و مهاجرت هم جدول را overtime_status_history ساخته؛ بدون این
    // خط، Eloquent نام را به overtime_status_histories جمع می‌بندد و کوئری خطا می‌دهد.
    protected $table = 'overtime_status_history';

    public $timestamps = false; // فقط created_at لازم است؛ ثابت می‌ماند

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = ['overtime_period_id', 'from_status', 'to_status', 'actor_id', 'note'];

    public function overtimePeriod(): BelongsTo
    {
        return $this->belongsTo(OvertimePeriod::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
