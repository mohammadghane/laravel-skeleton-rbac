<?php

namespace Tests\Feature;

use App\Models\ContractType;
use App\Models\Deputy;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * انتخابگرهای داینامیک با قابلیت جست‌وجو.
 *
 * خطر اصلی این تبدیل: کامپوننت مقدار را با <input type="hidden"> می‌فرستد نه
 * <select>. اگر مقدار فعلی به کامپوننت نرسد، ویرایش یک رکورد بی‌سروصدا واحد/سمت
 * کاربر را خالی می‌کند. این تست‌ها دقیقاً همان را قفل می‌کنند.
 */
class SearchableSelectTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $role = Role::firstOrCreate(['name' => 'admin']);
        $role->permissions()->syncWithoutDetaching([
            Permission::firstOrCreate(['key' => 'admin/users'], ['title' => 'u'])->id,
            Permission::firstOrCreate(['key' => 'menu/personnel'], ['title' => 'm'])->id,
            Permission::firstOrCreate(['key' => 'overtime/org-chart-manage'], ['title' => 'c'])->id,
            Permission::firstOrCreate(['key' => 'menu/units'], ['title' => 'mu'])->id,
        ]);

        $user = User::factory()->create(['username' => 'admin']);
        $user->roles()->attach($role->id);
        User::forgetPermissionCache($user->id);

        return $user;
    }

    public function test_the_user_form_uses_searchable_selects_instead_of_plain_ones(): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('admin.users.create'))
            ->assertOk()
            ->getContent();

        // چهار فیلد داینامیک: واحد، معاونت، سمت، نوع قرارداد
        $this->assertSame(4, substr_count($html, 'x-data="searchableSelect('));

        foreach (['unit_id', 'deputy_id', 'position_id', 'contract_type_id'] as $field) {
            $this->assertStringNotContainsString('<select name="' . $field . '"', $html);
            $this->assertMatchesRegularExpression(
                '/<input type="hidden" name="' . $field . '"/',
                $html,
                "فیلد {$field} باید مقدارش را با input مخفی بفرستد."
            );
        }
    }

    public function test_editing_keeps_the_current_selection(): void
    {
        // اگر مقدار فعلی به کامپوننت نرسد، ذخیره‌ی فرم واحد کاربر را پاک می‌کند.
        $deputy = Deputy::create(['title' => 'معاونت الف', 'is_active' => true]);
        $unit = Unit::create(['deputy_id' => $deputy->id, 'title' => 'واحد الف', 'is_active' => true]);
        $position = Position::create(['title' => 'کارشناس', 'is_active' => true]);
        $contract = ContractType::create(['title' => 'رسمی', 'is_active' => true]);

        $person = User::factory()->create([
            'username' => 'p1',
            'unit_id' => $unit->id,
            'position_id' => $position->id,
            'contract_type_id' => $contract->id,
        ]);

        $html = $this->actingAs($this->admin())
            ->get(route('admin.users.edit', $person))
            ->assertOk()
            ->getContent();

        foreach ([$unit->id, $position->id, $contract->id] as $expected) {
            $this->assertStringContainsString("selected: '{$expected}'", $html);
        }
    }

    public function test_a_saved_form_still_persists_the_selected_values(): void
    {
        // مهم‌ترین تضمین: چون مقدار از input مخفی می‌آید، ذخیره باید مثل قبل کار کند.
        $deputy = Deputy::create(['title' => 'معاونت ب', 'is_active' => true]);
        $unit = Unit::create(['deputy_id' => $deputy->id, 'title' => 'واحد ب', 'is_active' => true]);
        $position = Position::create(['title' => 'رئیس گروه', 'is_active' => true]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.users.store'), [
                'name' => 'سارا',
                'username' => 'sara',
                'unit_id' => $unit->id,
                'position_id' => $position->id,
            ])
            ->assertOk();

        $created = User::where('username', 'sara')->first();

        $this->assertSame($unit->id, $created->unit_id);
        $this->assertSame($position->id, $created->position_id);
    }

    public function test_the_unit_form_uses_searchable_selects(): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('admin.overtime.units.create'))
            ->assertOk()
            ->getContent();

        // معاونت + مدیر واحد
        $this->assertSame(2, substr_count($html, 'x-data="searchableSelect('));
        $this->assertStringNotContainsString('<select name="manager_user_id"', $html);
    }

    public function test_a_required_select_keeps_its_required_flag(): void
    {
        // معاونتِ واحد اجباری است؛ input مخفی باید required بماند وگرنه اعتبارسنجی
        // سمت مرورگر از بین می‌رود.
        $html = $this->actingAs($this->admin())
            ->get(route('admin.overtime.units.create'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<input type="hidden" name="deputy_id"[^>]*required/',
            $html
        );
    }
}
