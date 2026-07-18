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
}
