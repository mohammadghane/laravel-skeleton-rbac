<?php

namespace Tests\Feature;

use App\Models\ContractType;
use App\Models\Deputy;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Role;
use App\Models\SpecialItem;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * تست حذف گروهی در همه‌ی فهرست‌ها.
 *
 * قاعده‌ی محوری: حذف گروهی **میان‌بری برای دور زدن محدودیت‌های حذف تکی نیست**. مثلاً
 * حذف واحدی که پرسنل دارد، آن پرسنل را بدون مدیر (و در نتیجه بدون امکان ثبت اضافه‌کار)
 * رها می‌کند. بنابراین برای هر رکورد همان شرط بررسی می‌شود و موارد غیرمجاز با ذکر دلیل
 * گزارش می‌گردند - بدون آنکه کل عملیات رد شود.
 */
class BulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * دسترسی‌های لازم برای حذف گروهی: هم دسترسیِ **منو** (که مسیر را باز می‌کند) و هم
     * دسترسی **عملیاتی** (که اجازه‌ی تغییر داده می‌دهد) - نگاه کنید به config/permissions.php.
     */
    private function admin(array $permissions = [
        'menu/personnel', 'menu/deputies', 'menu/units', 'menu/positions',
        'menu/contract-types', 'menu/special-items',
        'admin/users', 'overtime/org-chart-manage', 'overtime/hr-manage',
    ]): User
    {
        $role = Role::firstOrCreate(['name' => 'admin']);

        foreach ($permissions as $key) {
            $permission = Permission::firstOrCreate(['key' => $key], ['title' => $key]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $admin = User::factory()->create(['username' => 'admin.bulk']);
        $admin->roles()->attach($role->id);
        User::forgetPermissionCache($admin->id);

        return $admin;
    }

    public function test_it_deletes_selected_positions(): void
    {
        $a = Position::create(['title' => 'سمت الف', 'is_active' => true]);
        $b = Position::create(['title' => 'سمت ب', 'is_active' => true]);
        $keep = Position::create(['title' => 'سمت ج', 'is_active' => true]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.overtime.positions.bulk-destroy'), ['ids' => [$a->id, $b->id]])
            ->assertOk()
            ->assertJson(['success' => true, 'deleted' => 2]);

        $this->assertSame(1, Position::count());
        $this->assertNotNull(Position::find($keep->id));
    }

    public function test_it_blocks_positions_that_still_have_personnel(): void
    {
        $busy = Position::create(['title' => 'سمت پرکاربرد', 'is_active' => true]);
        $free = Position::create(['title' => 'سمت خالی', 'is_active' => true]);
        User::factory()->create(['position_id' => $busy->id]);

        $response = $this->actingAs($this->admin())
            ->postJson(route('admin.overtime.positions.bulk-destroy'), ['ids' => [$busy->id, $free->id]])
            ->assertOk();

        $this->assertSame(1, $response->json('deleted'));
        $this->assertCount(1, $response->json('blocked'));
        $this->assertNotNull(Position::find($busy->id), 'سمت دارای پرسنل نباید حذف شود.');
        $this->assertNull(Position::find($free->id));

        // باگی که کاربر گزارش کرد: در حذفِ جزئی، سطرهای حذف‌شده تا رفرش دستی روی صفحه
        // می‌ماندند. برای برداشتن آن‌ها بدون رفرش، پاسخ باید شناسه‌شان را بگوید -
        // شمارنده‌ی deleted به‌تنهایی کافی نیست چون نمی‌گوید کدام‌ها موفق بوده‌اند.
        $this->assertSame([$free->id], $response->json('deleted_ids'));
    }

    public function test_deleted_ids_are_returned_for_every_bulk_delete(): void
    {
        $first = Position::create(['title' => 'سمت یک', 'is_active' => true]);
        $second = Position::create(['title' => 'سمت دو', 'is_active' => true]);

        $response = $this->actingAs($this->admin())
            ->postJson(route('admin.overtime.positions.bulk-destroy'), ['ids' => [$first->id, $second->id]])
            ->assertOk();

        $ids = $response->json('deleted_ids');
        sort($ids);

        $this->assertSame([$first->id, $second->id], $ids);
        $this->assertSame($response->json('deleted'), count($ids), 'شمارنده و فهرست شناسه‌ها باید هم‌خوان باشند.');
    }

    public function test_it_blocks_units_that_still_have_personnel(): void
    {
        $deputy = Deputy::create(['title' => 'معاونت آزمون', 'is_active' => true]);
        $busy = Unit::create(['deputy_id' => $deputy->id, 'title' => 'واحد پر', 'is_active' => true]);
        $free = Unit::create(['deputy_id' => $deputy->id, 'title' => 'واحد خالی', 'is_active' => true]);
        User::factory()->create(['unit_id' => $busy->id]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.overtime.units.bulk-destroy'), ['ids' => [$busy->id, $free->id]])
            ->assertOk()
            ->assertJson(['deleted' => 1]);

        $this->assertNotNull(Unit::find($busy->id));
        $this->assertNull(Unit::find($free->id));
    }

    public function test_it_blocks_deputies_that_still_have_units(): void
    {
        $busy = Deputy::create(['title' => 'معاونت پر', 'is_active' => true]);
        $free = Deputy::create(['title' => 'معاونت خالی', 'is_active' => true]);
        Unit::create(['deputy_id' => $busy->id, 'title' => 'واحد', 'is_active' => true]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.overtime.deputies.bulk-destroy'), ['ids' => [$busy->id, $free->id]])
            ->assertOk()
            ->assertJson(['deleted' => 1]);

        $this->assertNotNull(Deputy::find($busy->id));
        $this->assertNull(Deputy::find($free->id));
    }

    public function test_it_blocks_contract_types_in_use(): void
    {
        $busy = ContractType::create(['title' => 'رسمی', 'is_active' => true]);
        $free = ContractType::create(['title' => 'طرح', 'is_active' => true]);
        User::factory()->create(['contract_type_id' => $busy->id]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.overtime.contract-types.bulk-destroy'), ['ids' => [$busy->id, $free->id]])
            ->assertOk()
            ->assertJson(['deleted' => 1]);

        $this->assertNotNull(ContractType::find($busy->id));
    }

    public function test_it_deletes_special_items(): void
    {
        $a = SpecialItem::create(['title' => 'آیتم الف', 'submitter_role' => 'hr', 'default_value' => 0]);
        $b = SpecialItem::create(['title' => 'آیتم ب', 'submitter_role' => 'hr', 'default_value' => 0]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.overtime.special-items.bulk-destroy'), ['ids' => [$a->id, $b->id]])
            ->assertOk()
            ->assertJson(['deleted' => 2]);

        $this->assertSame(0, SpecialItem::count());
    }

    public function test_personnel_bulk_delete_protects_self_managers_and_deputies(): void
    {
        $admin = $this->admin();

        $deputy = Deputy::create(['title' => 'معاونت آزمون', 'is_active' => true]);
        $unit = Unit::create(['deputy_id' => $deputy->id, 'title' => 'واحد آزمون', 'is_active' => true]);

        $manager = User::factory()->create();
        $unit->update(['manager_user_id' => $manager->id]);

        $head = User::factory()->create();
        $deputy->update(['head_user_id' => $head->id]);

        $plain = User::factory()->create();

        $response = $this->actingAs($admin)
            ->postJson(route('admin.users.bulk-destroy'), [
                'ids' => [$admin->id, $manager->id, $head->id, $plain->id],
            ])
            ->assertOk();

        $this->assertSame(1, $response->json('deleted'), 'فقط پرسنل عادی باید حذف شود.');
        $this->assertCount(3, $response->json('blocked'));

        $this->assertNull(User::find($plain->id));
        $this->assertNotNull(User::find($admin->id), 'کاربر نباید حساب خودش را حذف کند.');
        $this->assertNotNull(User::find($manager->id), 'مدیر واحد نباید حذف شود.');
        $this->assertNotNull(User::find($head->id), 'معاون نباید حذف شود.');
    }

    public function test_it_requires_at_least_one_id(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('admin.overtime.positions.bulk-destroy'), ['ids' => []])
            ->assertStatus(422);
    }

    public function test_it_is_denied_without_permission(): void
    {
        $position = Position::create(['title' => 'سمت', 'is_active' => true]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.overtime.positions.bulk-destroy'), ['ids' => [$position->id]])
            ->assertForbidden();

        $this->assertNotNull(Position::find($position->id));
    }
}
