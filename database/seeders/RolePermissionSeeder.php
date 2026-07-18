<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('permissions') as $module => $configs) {
            foreach ($configs['perms'] as $configItems) {
                foreach ($configItems['items'] as $key => $item) {
                    Permission::updateOrCreate(
                        ['key' => $key],
                        ['module' => $module, 'title' => $item['title'] ?? $key]
                    );
                }
            }
        }

        // Old code auto-created a hidden "system-manager" super-admin role. Recreate it here.
        $admin = Role::firstOrCreate(
            ['name' => 'مدیر کل سیستم'],
            ['desk' => 'دسترسی کامل به تمام بخش ها', 'hidden' => true, 'is_default' => true]
        );
        $admin->syncPermissions([['key' => 'system-manager']]);
    }
}
