<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * تست دستور user:roles - راه بازیابی دسترسی از خط فرمان.
 *
 * اهمیتش: پنلِ مدیریت نقش‌ها خودش پشت دسترسی admin/users قفل است، پس اگر آخرین
 * مدیر سیستم دسترسی‌اش را از دست بدهد، بدون CLI هیچ راه بازگشتی وجود ندارد.
 */
class ManageUserRolesCommandTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $username = 'someone'): User
    {
        return User::factory()->create(['username' => $username]);
    }

    public function test_it_adds_a_role_without_removing_existing_ones(): void
    {
        $user = $this->user();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'user'])->id);
        Role::firstOrCreate(['name' => 'admin']);

        $this->artisan('user:roles', ['user' => 'someone', '--add' => 'admin'])
            ->assertSuccessful();

        $this->assertEqualsCanonicalizing(
            ['user', 'admin'],
            $user->fresh()->roles->pluck('name')->all()
        );
    }

    public function test_set_replaces_every_role(): void
    {
        $user = $this->user();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'user'])->id);
        Role::firstOrCreate(['name' => 'admin']);

        $this->artisan('user:roles', ['user' => 'someone', '--set' => 'admin'])
            ->assertSuccessful();

        $this->assertSame(['admin'], $user->fresh()->roles->pluck('name')->all());
    }

    public function test_it_removes_a_role(): void
    {
        $user = $this->user();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'admin'])->id);

        $this->artisan('user:roles', ['user' => 'someone', '--remove' => 'admin'])
            ->assertSuccessful();

        $this->assertEmpty($user->fresh()->roles);
    }

    public function test_an_unknown_role_fails_instead_of_silently_doing_nothing(): void
    {
        $user = $this->user();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'user'])->id);

        $this->artisan('user:roles', ['user' => 'someone', '--add' => 'no-such-role'])
            ->assertFailed();

        // نقش‌های قبلی باید دست‌نخورده بمانند.
        $this->assertSame(['user'], $user->fresh()->roles->pluck('name')->all());
    }

    public function test_an_unknown_user_fails(): void
    {
        $this->artisan('user:roles', ['user' => 'ghost'])->assertFailed();
    }

    public function test_it_can_restore_access_to_a_locked_out_administrator(): void
    {
        // سناریوی واقعیِ باگ: کاربری که همه‌ی نقش‌هایش پاک شده است.
        $admin = $this->user('admin');
        $role = Role::firstOrCreate(['name' => 'admin']);
        $role->permissions()->syncWithoutDetaching([
            Permission::firstOrCreate(['key' => 'system-manager'], ['title' => 'system-manager'])->id,
        ]);

        $this->assertFalse($admin->hasPermission('menu/personnel'));

        $this->artisan('user:roles', ['user' => 'admin', '--add' => 'admin'])
            ->assertSuccessful();

        $admin = $admin->fresh();
        User::forgetPermissionCache($admin->id);

        $this->assertTrue($admin->hasPermission('menu/personnel'), 'system-manager باید همه‌ی دسترسی‌ها را بدهد.');
    }

    public function test_list_roles_runs_without_a_user_argument(): void
    {
        Role::firstOrCreate(['name' => 'admin']);

        $this->artisan('user:roles', ['--list-roles' => true])->assertSuccessful();
    }
}
