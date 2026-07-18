<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory, Auditable;

    protected $fillable = ['name', 'desk', 'center_id', 'hidden', 'is_default'];

    protected $casts = [
        'hidden' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function auditLabel(): string
    {
        return __('نقش') . ': ' . $this->name;
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user')->withPivot('assigned_at');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_role')
            ->withPivot(['resource', 'pattern'])
            ->withTimestamps();
    }

    /**
     * Replace this role's permission set in one go (mirrors old Perms::savePerms()).
     * $items: array of ['key' => string, 'resource' => ?string, 'pattern' => ?string]
     */
    public function syncPermissions(array $items): void
    {
        $rows = [];
        foreach ($items as $item) {
            $permission = Permission::firstOrCreate(['key' => $item['key']]);
            $rows[$permission->id] = [
                'resource' => $item['resource'] ?? null,
                'pattern' => $item['pattern'] ?? null,
            ];
        }
        $this->permissions()->sync($rows);
    }
}
