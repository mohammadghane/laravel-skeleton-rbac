<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Order matters:
     *  1. Permissions must exist before roles can be granted them.
     *  2. Roles must exist before a user can be assigned one.
     *  3. OvertimeOrgSeeder only needs the users table to exist (its FKs are nullable),
     *     so it can safely run any time after migrations - placed last for clarity.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            AdminUserSeeder::class,
            OvertimeOrgSeeder::class,
        ]);
    }
}
