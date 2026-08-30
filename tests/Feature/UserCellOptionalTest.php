<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * شماره موبایل پرسنل اختیاری است (بسیاری از پرسنلِ آمده از وب‌سرویس شماره ندارند)، ولی
 * اگر وارد شود باید فرمت درست و یکتا داشته باشد.
 */
class UserCellOptionalTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $role = Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'user']); // نقش پیش‌فرض کاربر جدید
        $ids = collect(['admin/users', 'menu/personnel'])->map(
            fn ($key) => Permission::firstOrCreate(['key' => $key], ['title' => $key])->id
        );
        $role->permissions()->syncWithoutDetaching($ids->all());

        $admin = User::factory()->create(['username' => 'admin.cell']);
        $admin->roles()->attach($role->id);
        User::forgetPermissionCache($admin->id);

        return $admin;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'آزمون',
            'family' => 'تستی',
            'gender' => '1',
            'username' => 'u' . random_int(1000, 9999),
            'email' => 'u' . random_int(1000, 9999) . '@example.test',
            'password' => 'secret123',
        ], $overrides);
    }

    public function test_personnel_can_be_saved_without_a_mobile_number(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('admin.users.store'), $this->payload(['username' => 'no.cell']))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertNull(User::where('username', 'no.cell')->value('cell'));
    }

    public function test_an_empty_mobile_field_is_stored_as_null_not_empty_string(): void
    {
        // اگر '' ذخیره شود، به‌خاطر ایندکس یکتا فقط یک پرسنل می‌تواند بدون شماره باشد.
        $this->actingAs($this->admin())
            ->postJson(route('admin.users.store'), $this->payload(['username' => 'blank.cell', 'cell' => '']))
            ->assertOk();

        $this->assertNull(User::where('username', 'blank.cell')->value('cell'));
    }

    public function test_multiple_personnel_can_have_no_mobile_number(): void
    {
        $admin = $this->admin();

        foreach (['a.nocell', 'b.nocell', 'c.nocell'] as $username) {
            $this->actingAs($admin)
                ->postJson(route('admin.users.store'), $this->payload(['username' => $username, 'cell' => '']))
                ->assertOk()
                ->assertJson(['success' => true]);
        }

        $this->assertSame(3, User::whereIn('username', ['a.nocell', 'b.nocell', 'c.nocell'])->count());
    }

    public function test_an_invalid_mobile_number_is_still_rejected(): void
    {
        $admin = $this->admin();

        foreach (['0812345678', '12345', '091212345678'] as $bad) {
            $this->actingAs($admin)
                ->postJson(route('admin.users.store'), $this->payload(['cell' => $bad]))
                ->assertStatus(422)
                ->assertJsonValidationErrors('cell');
        }
    }

    public function test_a_valid_mobile_number_is_still_normalized_and_stored(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('admin.users.store'), $this->payload(['username' => 'has.cell', 'cell' => '۰۹۱۲۹۹۹۸۸۷۷']))
            ->assertOk();

        $this->assertSame('09129998877', User::where('username', 'has.cell')->value('cell'));
    }

    public function test_duplicate_mobile_numbers_are_still_rejected(): void
    {
        $admin = $this->admin();
        User::factory()->create(['cell' => '09121112233']);

        $this->actingAs($admin)
            ->postJson(route('admin.users.store'), $this->payload(['cell' => '09121112233']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('cell');
    }
}
