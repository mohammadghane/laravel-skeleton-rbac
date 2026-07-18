<?php

namespace App\Concerns;

use App\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;

/**
 * Add `use HasRoles;` to App\Models\User.
 *
 * Replaces the old acl_helper.php functions:
 *   isAllow($privilege)   -> $user->hasPermission($privilege)
 *   isAdmin()             -> $user->isAdmin()
 *   hasRole($role_id)     -> $user->hasRole($role_id)
 */
trait HasRoles
{
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')->withPivot('assigned_at');
    }

    /**
     * Mirrors the old isAllow() helper, including its "admin/reload-*" bypass pattern
     * and the special 'system-manager' super-admin privilege.
     */
    public function hasPermission(string $privilege, ?string $resource = null): bool
    {
        if (preg_match('/^admin\/reload-/', $privilege)) {
            return true;
        }

        if ($this->isAdmin()) {
            return true;
        }

        return in_array($privilege, $this->permissionKeys($resource), true);
    }

    public function isAdmin(): bool
    {
        return in_array('system-manager', $this->permissionKeys(), true);
    }

    public function hasRole(int $roleId): bool
    {
        return $this->roles->contains('id', $roleId);
    }

    /**
     * Cached, per-request-ish list of this user's permission keys (across all roles).
     * Cleared automatically whenever roles/permissions change via Role::syncPermissions()
     * if you wire up a model event, or simply call User::forgetPermissionCache($user->id).
     */
    protected function permissionKeys(?string $resource = null): array
    {
        return Cache::remember(
            "user:{$this->id}:permissions" . ($resource ? ":{$resource}" : ''),
            now()->addMinutes(10),
            function () use ($resource) {
                $query = $this->roles()->with('permissions');
                $roles = $query->get();

                $keys = [];
                foreach ($roles as $role) {
                    foreach ($role->permissions as $permission) {
                        if ($resource !== null && $permission->pivot->resource && $permission->pivot->resource !== $resource) {
                            continue;
                        }
                        $keys[] = $permission->key;
                    }
                }
                return array_unique($keys);
            }
        );
    }

    public static function forgetPermissionCache(int $userId): void
    {
        Cache::forget("user:{$userId}:permissions");
    }
}
