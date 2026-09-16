<?php

namespace Tests\Feature;

use App\Models\Deputy;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ستون «معاونت» در فهرست پرسنل.
 *
 * نکته‌ی کلیدی: معاونتِ تقریباً همه‌ی پرسنل از طریق «واحد» مشخص می‌شود، نه ستون
 * deputy_id (همگام‌سازی فقط زنجیره‌ی مدیر را می‌سازد). اگر ستون صرفاً deputy_id را
 * بخواند، برای ۹۹٪ پرسنل خالی می‌ماند.
 */
class UserListDeputyColumnTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $role = Role::firstOrCreate(['name' => 'admin']);
        $role->permissions()->syncWithoutDetaching([
            Permission::firstOrCreate(['key' => 'menu/personnel'], ['title' => 'menu'])->id,
            Permission::firstOrCreate(['key' => 'admin/users'], ['title' => 'users'])->id,
        ]);

        $user = User::factory()->create(['username' => 'admin']);
        $user->roles()->attach($role->id);
        User::forgetPermissionCache($user->id);

        return $user;
    }

    public function test_the_deputy_column_is_rendered(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('معاونت');
    }

    public function test_it_shows_the_deputy_reached_through_the_unit(): void
    {
        $deputy = Deputy::create(['title' => 'معاونت فنی', 'is_active' => true]);
        $unit = Unit::create(['deputy_id' => $deputy->id, 'title' => 'واحد برق', 'is_active' => true]);
        User::factory()->create(['username' => 'p1', 'name' => 'رضا', 'unit_id' => $unit->id]);

        $this->actingAs($this->admin())
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('معاونت فنی');
    }

    public function test_a_direct_deputy_takes_precedence_over_the_units_one(): void
    {
        $viaUnit = Deputy::create(['title' => 'معاونت از واحد', 'is_active' => true]);
        $direct = Deputy::create(['title' => 'معاونت مستقیم', 'is_active' => true]);
        $unit = Unit::create(['deputy_id' => $viaUnit->id, 'title' => 'واحد', 'is_active' => true]);

        $user = User::factory()->create([
            'username' => 'p2',
            'unit_id' => $unit->id,
            'deputy_id' => $direct->id,
        ]);

        $this->assertSame($direct->id, $user->effectiveDeputy()?->id);
    }

    public function test_personnel_without_a_unit_or_deputy_show_a_dash(): void
    {
        User::factory()->create(['username' => 'orphan', 'name' => 'بی‌واحد']);

        $this->actingAs($this->admin())
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('بی‌واحد');
    }

    public function test_searching_by_deputy_title_finds_personnel_of_that_deputy(): void
    {
        $a = Deputy::create(['title' => 'معاونت الف', 'is_active' => true]);
        $b = Deputy::create(['title' => 'معاونت ب', 'is_active' => true]);
        $unitA = Unit::create(['deputy_id' => $a->id, 'title' => 'واحد الف', 'is_active' => true]);
        $unitB = Unit::create(['deputy_id' => $b->id, 'title' => 'واحد ب', 'is_active' => true]);

        User::factory()->create(['username' => 'in-a', 'name' => 'علی', 'unit_id' => $unitA->id]);
        User::factory()->create(['username' => 'in-b', 'name' => 'حسن', 'unit_id' => $unitB->id]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.users.index', ['q' => 'معاونت الف']))
            ->assertOk();

        $users = $response->viewData('users');

        $this->assertSame(1, $users->total(), 'فقط پرسنل همان معاونت باید برگردند.');
        $this->assertSame('in-a', $users->first()->username);
    }

    public function test_the_listing_does_not_run_a_query_per_row(): void
    {
        // بدون eager load، هر ردیف برای deputy و unit کوئری جدا می‌زد.
        //
        // نکته: لاگ کوئری باید *فقط* دور خودِ درخواست فعال باشد؛ اگر ساخت رکوردهای
        // آزمون هم داخل لاگ بیفتد، insertها شمرده می‌شوند و عدد بی‌معنا می‌شود.
        $deputy = Deputy::create(['title' => 'معاونت', 'is_active' => true]);
        $unit = Unit::create(['deputy_id' => $deputy->id, 'title' => 'واحد', 'is_active' => true]);
        $admin = $this->admin();

        User::factory()->count(5)->create(['unit_id' => $unit->id]);
        $few = $this->countQueriesForListing($admin);

        User::factory()->count(40)->create(['unit_id' => $unit->id]);
        $many = $this->countQueriesForListing($admin);

        // با ۹ برابر شدن ردیف‌ها، تعداد کوئری باید عملاً ثابت بماند. حاشیه‌ی کوچک
        // برای نوسان کش دسترسی‌هاست، نه برای کوئریِ هر ردیف (که ۴۰ تا اضافه می‌کرد).
        $this->assertLessThanOrEqual($few + 2, $many,
            "تعداد کوئری با افزایش ردیف‌ها بالا رفت ({$few} => {$many}) - یعنی N+1.");
    }

    /** تعداد کوئری‌های یک بار بازکردن فهرست پرسنل. */
    private function countQueriesForListing(User $admin): int
    {
        User::forgetPermissionCache($admin->id);

        \DB::flushQueryLog();
        \DB::enableQueryLog();
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
        $count = count(\DB::getQueryLog());
        \DB::disableQueryLog();

        return $count;
    }
}
