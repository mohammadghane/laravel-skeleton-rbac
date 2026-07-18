<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * One row per privilege key defined in config/permissions.php.
     * Idempotent (updateOrCreate) - safe to re-run every time you add a new route/permission.
     */
    public function run(): void
    {
        foreach (config('permissions') as $module => $moduleConfig) {
            foreach ($moduleConfig['perms'] as $group) {
                foreach ($group['items'] as $key => $item) {
                    Permission::updateOrCreate(
                        ['key' => $key],
                        ['module' => $module, 'title' => $item['title'] ?? $key]
                    );
                }
            }
        }

        $this->command?->info('Permissions seeded: ' . Permission::count());
    }
}
