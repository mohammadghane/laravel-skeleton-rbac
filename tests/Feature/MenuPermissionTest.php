<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * تست ساختار دسترسی «هر منو یک دسترسی مستقل».
 *
 * قاعده‌ی محوری: پنهان‌کردن منو در سایدبار کافی نیست - مسیر هم باید ۴۰۳ بدهد، وگرنه
 * کاربر با تایپ مستقیم آدرس به همان صفحه می‌رسد و کنترل دسترسی صرفاً ظاهری می‌شود.
 */
class MenuPermissionTest extends TestCase
{
    use RefreshDatabase;

    /** نگاشت دسترسی منو به یک مسیر نمونه از همان منو. */
    private const MENU_ROUTES = [
        'menu/personnel' => 'admin.users.index',
        'menu/roles' => 'admin.roles.index',
        'menu/audit-logs' => 'admin.audit-logs.index',
        'menu/deputies' => 'admin.overtime.deputies.index',
        'menu/units' => 'admin.overtime.units.index',
        'menu/positions' => 'admin.overtime.positions.index',
        'menu/contract-types' => 'admin.overtime.contract-types.index',
        'menu/special-items' => 'admin.overtime.special-items.index',
        'menu/import' => 'admin.overtime.import.form',
        'menu/personnel-sync' => 'admin.overtime.sync.index',
        'menu/dashboard' => 'admin.overtime.dashboard',
        'menu/overtime-entry' => 'admin.overtime.periods.index',
        'menu/capacity-transfers' => 'admin.overtime.capacity-transfers.index',
    ];

    /** کاربری با مجموعه‌ی دقیقی از دسترسی‌ها می‌سازد. */
    private function userWith(array $permissionKeys): User
    {
        $role = Role::create(['name' => 'role-' . uniqid()]);

        $ids = collect($permissionKeys)->map(
            fn ($key) => Permission::firstOrCreate(['key' => $key], ['title' => $key])->id
        );
        $role->permissions()->sync($ids);

        $user = User::factory()->create();
        $user->roles()->attach($role->id);
        User::forgetPermissionCache($user->id);

        return $user;
    }

    public function test_each_menu_has_its_own_permission_in_config(): void
    {
        $configured = collect(config('permissions.menus.perms.general.items'))->keys();

        foreach (array_keys(self::MENU_ROUTES) as $key) {
            $this->assertTrue($configured->contains($key), "دسترسی {$key} در config/permissions.php تعریف نشده است.");
        }

        $this->assertCount(13, $configured, 'باید دقیقاً ۱۳ دسترسی منو وجود داشته باشد.');
    }

    public function test_a_user_can_only_reach_menus_their_role_allows(): void
    {
        // فقط دو دسترسی: بقیه باید ۴۰۳ بدهند.
        $user = $this->userWith(['menu/positions', 'menu/units']);

        foreach (self::MENU_ROUTES as $permission => $route) {
            $expected = in_array($permission, ['menu/positions', 'menu/units'], true) ? 200 : 403;

            $this->actingAs($user)
                ->get(route($route))
                ->assertStatus($expected, "مسیر {$route} باید {$expected} برگرداند.");
        }
    }

    public function test_hidden_menus_are_blocked_at_the_route_not_just_the_sidebar(): void
    {
        $user = $this->userWith(['menu/positions']);

        // سایدبار فقط «سمت سازمانی» را نشان می‌دهد…
        $html = $this->actingAs($user)->get(route('admin.overtime.positions.index'))->getContent();
        $this->assertStringNotContainsString('معاونت‌ها', $html);

        // …و تایپ مستقیم آدرس هم ۴۰۳ می‌گیرد.
        $this->actingAs($user)->get(route('admin.overtime.deputies.index'))->assertForbidden();
    }

    public function test_system_manager_reaches_every_menu(): void
    {
        $admin = $this->userWith(['system-manager']);

        foreach (self::MENU_ROUTES as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk();
        }
    }

    public function test_a_user_without_any_permission_sees_no_menu(): void
    {
        $user = $this->userWith([]);

        foreach (self::MENU_ROUTES as $route) {
            $this->actingAs($user)->get(route($route))->assertForbidden();
        }
    }
}
