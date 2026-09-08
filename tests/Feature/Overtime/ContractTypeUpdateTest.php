<?php

namespace Tests\Feature\Overtime;

use App\Models\ContractType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ذخیره‌ی یک قرارداد با عنوانِ بدون تغییر نباید خطای «تکراری» بدهد.
 *
 * باگ: قانون unique با ignore($id) نوشته شده بود، ولی شناسه از
 * route('contract_type') خوانده می‌شد در حالی که نام پارامتر در مسیر
 * {contractType} است. مقدار null برمی‌گشت، ignore بی‌اثر می‌ماند و رکورد با
 * خودش تداخل پیدا می‌کرد.
 *
 * چون نام‌ها بی‌سروصدا null می‌دهند (نه خطا)، تست باید مسیر واقعی HTTP را بزند؛
 * صدازدن مستقیم rules() این باگ را نشان نمی‌داد.
 */
class ContractTypeUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        $role = Role::firstOrCreate(['name' => 'chart-manager']);
        $role->permissions()->syncWithoutDetaching([
            Permission::firstOrCreate(['key' => 'overtime/org-chart-manage'], ['title' => 'chart'])->id,
            Permission::firstOrCreate(['key' => 'menu/contract-types'], ['title' => 'menu'])->id,
        ]);

        $user = User::factory()->create(['username' => 'chart-manager']);
        $user->roles()->attach($role->id);
        User::forgetPermissionCache($user->id);

        return $user;
    }

    public function test_saving_a_contract_type_with_an_unchanged_title_succeeds(): void
    {
        $type = ContractType::create(['title' => 'رسمی', 'is_active' => true]);

        $this->actingAs($this->manager())
            ->putJson(route('admin.overtime.contract-types.update', $type), [
                'title' => 'رسمی',
                'is_active' => false,
            ])
            ->assertOk();

        $this->assertFalse((bool) $type->fresh()->is_active, 'تغییر باید ذخیره شود.');
    }

    public function test_the_title_can_still_be_changed(): void
    {
        $type = ContractType::create(['title' => 'رسمی', 'is_active' => true]);

        $this->actingAs($this->manager())
            ->putJson(route('admin.overtime.contract-types.update', $type), [
                'title' => 'پیمانی',
                'is_active' => true,
            ])
            ->assertOk();

        $this->assertSame('پیمانی', $type->fresh()->title);
    }

    public function test_a_duplicate_title_is_still_rejected(): void
    {
        // رفع باگ نباید محافظت واقعی را از بین ببرد.
        ContractType::create(['title' => 'رسمی', 'is_active' => true]);
        $other = ContractType::create(['title' => 'حجمی', 'is_active' => true]);

        $this->actingAs($this->manager())
            ->putJson(route('admin.overtime.contract-types.update', $other), [
                'title' => 'رسمی',
                'is_active' => true,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_creating_a_contract_type_with_an_existing_title_is_rejected(): void
    {
        ContractType::create(['title' => 'رسمی', 'is_active' => true]);

        $this->actingAs($this->manager())
            ->postJson(route('admin.overtime.contract-types.store'), [
                'title' => 'رسمی',
                'is_active' => true,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }
}
