<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\HasRoles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes, HasRoles, Auditable;

    protected $fillable = [
        'name', 'family', 'gender', 'username', 'email', 'password',
        'cell', 'tell', 'national_code', 'addr', 'province', 'city', 'zip_code',
        'center_id', 'agency_id', 'is_agency', 'is_provider', 'setter', 'set_with',
        // چارت سازمانی ماژول اضافه‌کار (مدیرعامل -> معاونت -> واحد -> پرسنل). center_id/agency_id
        // قدیمی برای این ماژول استفاده نمی‌شوند و عمداً دست‌نخورده مانده‌اند.
        'unit_id', 'deputy_id', 'position_id', 'contract_type_id',
        // همگام‌سازی با وب‌سرویس پرسنلی: EmpCode و پستِ سازمانی (PostID/PostCode).
        // پست، جایگاه یک فرد است؛ عنوان شغلی در جدول positions نگهداری می‌شود.
        'personnel_code', 'post_id', 'post_code', 'synced_at',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    // System-generated on create, not meaningful edits - keep the audit log focused on
    // fields an admin actually intended to change.
    protected array $auditExcept = ['sana_code', 'setter', 'set_with', 'api_token'];

    public function auditLabel(): string
    {
        return __('کاربر') . ': ' . trim("{$this->name} {$this->family}");
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_agency' => 'boolean',
            'is_provider' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | چارت سازمانی (ماژول اضافه‌کار پرسنل)
    |--------------------------------------------------------------------------
    | دقیقاً یکی از unit()/deputy() باید مقدار داشته باشد: پرسنلِ دارای واحد، مستقیم
    | زیرمجموعه‌ی معاونتِ همان واحد محسوب می‌شوند؛ پرسنلِ بدون واحد مستقیم زیر یک
    | معاونت‌اند. این قاعده در FormRequest مربوطه (نه در این مدل) اعتبارسنجی می‌شود.
    */

    public function unit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Unit::class);
    }

    public function deputy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Deputy::class);
    }

    public function position(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Position::class);
    }

    public function contractType(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\ContractType::class);
    }

    /** اگر این کاربر مدیر یک واحد باشد، آن واحد را برمی‌گرداند (یک نفر = مدیر دقیقاً یک واحد). */
    public function managedUnit(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\Unit::class, 'manager_user_id');
    }

    /** اگر این کاربر معاون یک معاونت باشد، آن معاونت را برمی‌گرداند. */
    public function headedDeputy(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\Deputy::class, 'head_user_id');
    }

    public function overtimePeriods(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\OvertimePeriod::class, 'personnel_id');
    }

    /** شناسه‌ی معاونتی که این پرسنل واقعاً زیرمجموعه‌ی آن است - چه مستقیم، چه از طریق واحد. */
    public function effectiveDeputyId(): ?int
    {
        if ($this->deputy_id) {
            return $this->deputy_id;
        }

        return $this->unit?->deputy_id;
    }

    /**
     * خودِ معاونتِ مؤثر (نه فقط شناسه‌اش) - برای نمایش در فهرست‌ها.
     *
     * همان قاعده‌ی effectiveDeputyId: معاونتِ مستقیم مقدم است، وگرنه از واحد خوانده
     * می‌شود. در عمل تقریباً همه‌ی پرسنل از مسیر دوم می‌آیند، چون همگام‌سازی فقط
     * زنجیره‌ی مدیر را می‌سازد و deputy_id را مستقیم پر نمی‌کند.
     */
    public function effectiveDeputy(): ?\App\Models\Deputy
    {
        return $this->deputy ?? $this->unit?->deputy;
    }

    /** آیا این کاربر مدیر یک واحد است؟ (نقش مدیر واحد از چارت استخراج می‌شود، نه از جدول roles) */
    public function isUnitManager(): bool
    {
        return \App\Models\Unit::where('manager_user_id', $this->id)->exists();
    }

    /** آیا این کاربر معاون یک معاونت است؟ */
    public function isDeputyHead(): bool
    {
        return \App\Models\Deputy::where('head_user_id', $this->id)->exists();
    }
}
