<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run PermissionSeeder first - this depends on the `permissions` table being populated.
     *
     * `is_default` = true protects these three from deletion in the UI
     * (see #params.delete.visible logic in the roles grid).
     */
    public function run(): void
    {
        $admin = Role::updateOrCreate(
            ['name' => 'admin'],
            ['desk' => 'دسترسی کامل به تمام بخش های سیستم', 'hidden' => false, 'is_default' => true]
        );
        // 'system-manager' is the super-admin bypass wired up in AclServiceProvider's
        // Gate::before() - holding it grants every permission without listing them all.
        $admin->syncPermissions([['key' => 'system-manager']]);

        // Baseline authenticated role. UserController::store() falls back to this role
        // for any new user created by someone without 'admin/users' (self-registration case).
        Role::updateOrCreate(
            ['name' => 'user'],
            ['desk' => 'کاربر عادی', 'hidden' => false, 'is_default' => true]
        );

        // Minimal/read-only placeholder role - no permissions granted by default.
        // Assign specific permissions to it once you know what a guest should see.
        Role::updateOrCreate(
            ['name' => 'guest'],
            ['desk' => 'دسترسی محدود / مهمان', 'hidden' => false, 'is_default' => true]
        );

        $this->command?->info('Roles seeded: admin, user, guest');
    }
}
