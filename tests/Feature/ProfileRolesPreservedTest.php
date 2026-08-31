<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ذخیره‌ی پروفایل نباید نقش‌های کاربر را دست بزند.
 *
 * باگی که این تست‌ها قفلش می‌کنند: فرم پروفایل عمداً فیلد «نقش سازمانی» را نشان
 * نمی‌دهد (کاربر نباید بتواند نقش خودش را عوض کند)، پس roles[] اصلاً ارسال نمی‌شود.
 * ولی store() نبودِ roles را با «کاربر نقشی انتخاب نکرده» اشتباه می‌گرفت و نقش
 * پیش‌فرض user را sync می‌کرد - یعنی مدیر سیستم با عوض‌کردن رمز خودش، همه‌ی
 * دسترسی‌هایش را از دست می‌داد.
 */
class ProfileRolesPreservedTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $role = Role::firstOrCreate(['name' => 'admin']);
        $role->permissions()->syncWithoutDetaching([
            Permission::firstOrCreate(['key' => 'admin/users'], ['title' => 'admin/users'])->id,
            Permission::firstOrCreate(['key' => 'menu/personnel'], ['title' => 'menu/personnel'])->id,
        ]);

        // نقش پیش‌فرضی که باگ به آن fallback می‌کرد باید وجود داشته باشد،
        // وگرنه تست باگ را بازتولید نمی‌کند.
        Role::firstOrCreate(['name' => 'user']);

        $user = User::factory()->create(['username' => 'admin']);
        $user->roles()->attach($role->id);
        User::forgetPermissionCache($user->id);

        return $user;
    }

    /** داده‌ی حداقلیِ لازم برای گذشتن از اعتبارسنجی فرم پروفایل. */
    private function profilePayload(User $user, array $overrides = []): array
    {
        return array_merge([
            'id' => $user->id,
            'name' => $user->name,
            'family' => $user->family,
            'username' => $user->username,
            'email' => $user->email,
            'gender' => $user->gender ?? 1,
        ], $overrides);
    }

    public function test_changing_own_password_keeps_all_roles(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->postJson(route('admin.profile.store'), $this->profilePayload($admin, [
                'password' => 'new-secret-123',
                'password_confirmation' => 'new-secret-123',
            ]))
            ->assertOk();

        $admin->refresh();
        User::forgetPermissionCache($admin->id);

        $this->assertSame(['admin'], $admin->roles()->pluck('name')->all(), 'نقش admin نباید حذف شود.');
        $this->assertTrue($admin->hasPermission('admin/users'), 'دسترسی‌ها باید حفظ شوند.');
    }

    public function test_saving_profile_without_password_keeps_roles(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->postJson(route('admin.profile.store'), $this->profilePayload($admin, ['name' => 'نام تازه']))
            ->assertOk();

        $admin->refresh();

        $this->assertSame(['admin'], $admin->roles()->pluck('name')->all());
        $this->assertSame('نام تازه', $admin->name);
    }

    public function test_a_user_cannot_grant_themselves_a_role_through_the_profile_form(): void
    {
        // فرم پروفایل فیلد نقش ندارد، ولی نبودِ فیلد در UI محافظت نیست: اگر کسی
        // دستی roles[] بفرستد هم نباید نقشش عوض شود.
        $admin = $this->adminUser();
        $superRole = Role::firstOrCreate(['name' => 'system-manager']);

        $this->actingAs($admin)
            ->postJson(route('admin.profile.store'), $this->profilePayload($admin, [
                'roles' => [$superRole->id],
            ]))
            ->assertOk();

        $admin->refresh();

        $this->assertSame(['admin'], $admin->roles()->pluck('name')->all(),
            'ارسال دستی roles[] به مسیر پروفایل نباید نقش را تغییر دهد.');
    }

    public function test_admin_editing_another_user_can_still_change_roles(): void
    {
        // رفع باگ نباید قابلیت واقعیِ مدیریت نقش‌ها در فرم کاربران را از بین ببرد.
        $admin = $this->adminUser();
        $target = User::factory()->create(['username' => 'target-user']);
        $editor = Role::firstOrCreate(['name' => 'editor']);

        $this->actingAs($admin)
            ->postJson(route('admin.users.store'), $this->profilePayload($target, [
                'roles' => [$editor->id],
            ]))
            ->assertOk();

        $this->assertSame(['editor'], $target->fresh()->roles()->pluck('name')->all());
    }
}
