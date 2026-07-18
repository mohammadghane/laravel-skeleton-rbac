<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run RoleSeeder first - this depends on the 'admin' role existing.
     *
     * WARNING: '123456' is a throwaway password for local/staging bootstrap only.
     * Change it (or force a reset) before this ever touches production.
     */
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Admin',
                'family' => 'User',
                'gender' => 1,
                'email' => 'admin@example.com',
                'password' => Hash::make('123456'),
                'cell' => '9120000000', // placeholder, unique - change to a real number
                'is_agency' => false,
                'is_provider' => false,
            ]
        );

        if (empty($admin->sana_code)) {
            $admin->sana_code = random_int(101, 999) . 'C' . $admin->id;
            $admin->save();
        }

        $adminRoleId = Role::where('name', 'admin')->value('id');
        $admin->roles()->syncWithoutDetaching([$adminRoleId]);

        $this->command?->info('Admin user seeded: username=admin / password=123456 (change this immediately)');
    }
}
