<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * تست «تعداد رکورد در هر صفحه» در فهرست پرسنل.
 *
 * نکته‌ی امنیتی: مقدار per_page فقط از فهرست مجاز پذیرفته می‌شود؛ در غیر این صورت یک
 * درخواست دستکاری‌شده مثل ?per_page=1000000 کل جدول را در حافظه بارگذاری می‌کرد.
 */
class UserListPaginationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $role = Role::firstOrCreate(['name' => 'admin']);
        $ids = collect(['admin/users', 'menu/personnel'])->map(
            fn ($key) => Permission::firstOrCreate(['key' => $key], ['title' => $key])->id
        );
        $role->permissions()->syncWithoutDetaching($ids->all());

        $admin = User::factory()->create(['username' => 'admin.page']);
        $admin->roles()->attach($role->id);
        User::forgetPermissionCache($admin->id);

        return $admin;
    }

    public function test_default_page_size_is_twenty(): void
    {
        User::factory()->count(30)->create();

        $response = $this->actingAs($this->admin())->get(route('admin.users.index'))->assertOk();

        $this->assertSame(20, $response->viewData('users')->perPage());
    }

    public function test_it_accepts_allowed_page_sizes(): void
    {
        User::factory()->count(60)->create();
        $admin = $this->admin();

        foreach ([20, 50, 100, 200] as $size) {
            $response = $this->actingAs($admin)
                ->get(route('admin.users.index', ['per_page' => $size]))
                ->assertOk();

            $this->assertSame($size, $response->viewData('users')->perPage());
        }
    }

    public function test_it_rejects_values_outside_the_allow_list(): void
    {
        User::factory()->count(5)->create();
        $admin = $this->admin();

        // ۹۹۹۹۹۹ خطرناک‌ترین حالت است: بدون فهرست مجاز، کل جدول بارگذاری می‌شد.
        foreach ([999999, 0, -5, 37, 'abc'] as $bad) {
            $response = $this->actingAs($admin)
                ->get(route('admin.users.index', ['per_page' => $bad]))
                ->assertOk();

            $this->assertSame(20, $response->viewData('users')->perPage(), "per_page={$bad} باید رد شود.");
        }
    }

    public function test_page_size_survives_search_and_paging(): void
    {
        User::factory()->count(80)->create(['family' => 'آزمونی']);
        $admin = $this->admin();

        $response = $this->actingAs($admin)
            ->get(route('admin.users.index', ['q' => 'آزمونی', 'per_page' => 50, 'page' => 2]))
            ->assertOk();

        $users = $response->viewData('users');
        $this->assertSame(50, $users->perPage());
        $this->assertSame(2, $users->currentPage());

        // لینک‌های صفحه‌بندی باید هر دو پارامتر را حفظ کنند. صفحه‌ی ۲ آخرین صفحه است
        // (۸۰ رکورد با ۵۰ در هر صفحه)، پس لینک «قبلی» را بررسی می‌کنیم نه «بعدی».
        $previous = $users->previousPageUrl() ?? '';
        $this->assertStringContainsString('per_page=50', $previous);
        $this->assertStringContainsString('q=', $previous);
    }

    public function test_search_also_matches_organizational_position(): void
    {
        // ستون «سمت سازمانی» در جدول نمایش داده می‌شود، پس کاربر انتظار دارد قابل
        // جست‌وجو باشد - بدون این، جست‌وجوی «کارشناس» هیچ نتیجه‌ای نمی‌داد.
        $position = Position::create(['title' => 'کارشناس منابع آب', 'is_active' => true]);
        User::factory()->count(3)->create(['position_id' => $position->id]);
        User::factory()->count(2)->create();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.users.index', ['q' => 'کارشناس']))
            ->assertOk();

        $this->assertSame(3, $response->viewData('users')->total());
    }
}
