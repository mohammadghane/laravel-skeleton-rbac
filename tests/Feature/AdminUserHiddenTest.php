<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * حساب سرویسیِ `admin` پرسنل سازمان نیست و نباید در فهرست پرسنل دیده شود.
 *
 * پنهان‌سازی در فهرست به‌تنهایی کافی نیست: ویرایش و حذف مستقیم هم باید مسدود باشد،
 * وگرنه کاربر با تایپ آدرس می‌تواند همان حساب را تغییر دهد یا حذف کند.
 */
class AdminUserHiddenTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        $role = Role::create(['name' => 'manager']);
        Role::firstOrCreate(['name' => 'user']);

        $ids = collect(['menu/personnel', 'admin/users'])->map(
            fn ($key) => Permission::firstOrCreate(['key' => $key], ['title' => $key])->id
        );
        $role->permissions()->sync($ids);

        $user = User::factory()->create(['username' => 'hr.manager']);
        $user->roles()->attach($role->id);
        User::forgetPermissionCache($user->id);

        return $user;
    }

    public function test_admin_is_not_listed_among_personnel(): void
    {
        User::factory()->create(['username' => 'admin', 'name' => 'مدیرسیستم']);
        User::factory()->create(['username' => 'staff.one', 'name' => 'کارمند']);

        $response = $this->actingAs($this->manager())->get(route('admin.users.index'))->assertOk();

        $usernames = $response->viewData('users')->pluck('username')->all();

        $this->assertNotContains('admin', $usernames, 'حساب admin نباید در فهرست پرسنل باشد.');
        $this->assertContains('staff.one', $usernames);
    }

    public function test_admin_cannot_be_opened_for_editing_by_someone_else(): void
    {
        $admin = User::factory()->create(['username' => 'admin']);

        $this->actingAs($this->manager())
            ->get(route('admin.users.edit', $admin))
            ->assertNotFound();
    }

    public function test_admin_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['username' => 'admin']);

        $this->actingAs($this->manager())
            ->deleteJson(route('admin.users.destroy', $admin))
            ->assertNotFound();

        $this->assertNotNull(User::where('username', 'admin')->first());
    }

    public function test_admin_is_skipped_in_bulk_delete(): void
    {
        $admin = User::factory()->create(['username' => 'admin']);
        $staff = User::factory()->create(['username' => 'staff.two']);

        $response = $this->actingAs($this->manager())
            ->postJson(route('admin.users.bulk-destroy'), ['ids' => [$admin->id, $staff->id]])
            ->assertOk();

        $this->assertSame(1, $response->json('deleted'));
        $this->assertNotNull(User::where('username', 'admin')->first(), 'admin نباید حذف شود.');
        $this->assertNull(User::where('username', 'staff.two')->first());
    }

    public function test_admin_can_still_edit_their_own_account(): void
    {
        $admin = User::factory()->create(['username' => 'admin']);
        Permission::firstOrCreate(['key' => 'menu/personnel'], ['title' => 'p']);
        Permission::firstOrCreate(['key' => 'system-manager'], ['title' => 's']);

        $role = Role::create(['name' => 'super']);
        $role->permissions()->sync(Permission::whereIn('key', ['menu/personnel', 'system-manager'])->pluck('id'));
        $admin->roles()->attach($role->id);
        User::forgetPermissionCache($admin->id);

        $this->actingAs($admin)->get(route('admin.users.edit', $admin))->assertOk();
    }
}
