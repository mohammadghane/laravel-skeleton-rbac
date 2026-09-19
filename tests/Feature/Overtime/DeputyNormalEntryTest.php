<?php

namespace Tests\Feature\Overtime;

use App\Enums\OvertimePeriodStatus;
use App\Models\ContractType;
use App\Models\Deputy;
use App\Models\OvertimePeriod;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Support\Jalali;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * معاونت باید بتواند «در صورت لزوم» اضافه‌کار واحدهای زیرمجموعه‌اش را ثبت کند -
 * مثلاً وقتی واحدی مدیر ندارد یا مدیر در دسترس نیست.
 *
 * این دسترسی جایگزین مدیر واحد نیست، پوششِ اوست: همان قواعد سقف، پنجره‌ی زمانی و
 * وضعیت دوره اعمال می‌شود.
 */
class DeputyNormalEntryTest extends TestCase
{
    use RefreshDatabase;

    private int $year;
    private int $month;
    private Deputy $deputy;
    private Unit $unit;
    private Position $position;
    private ContractType $contractType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->year = Jalali::currentYear();
        $this->month = Jalali::currentMonth();

        $this->deputy = Deputy::create(['title' => 'معاونت فنی', 'is_active' => true]);
        $this->unit = Unit::create(['deputy_id' => $this->deputy->id, 'title' => 'واحد برق', 'is_active' => true]);

        $this->contractType = ContractType::create(['title' => 'رسمی', 'is_active' => true]);
        $this->contractType->ceilingVersions()->create([
            'jalali_year' => $this->year, 'jalali_month' => $this->month,
            'ceiling_per_person' => 200, 'overall_ceiling' => 250, 'activation_day' => 1,
        ]);

        $this->position = Position::create(['title' => 'کارشناس', 'is_active' => true]);
        $this->position->ceilingVersions()->create([
            'jalali_year' => $this->year, 'jalali_month' => $this->month,
            'ceiling_per_person' => 90, 'manager_ceiling_per_person' => 90,
        ]);
    }

    private function deputyHead(): User
    {
        $role = Role::firstOrCreate(['name' => 'deputy-head']);
        $role->permissions()->syncWithoutDetaching([
            Permission::firstOrCreate(['key' => 'menu/overtime-entry'], ['title' => 'e'])->id,
        ]);

        $user = User::factory()->create(['username' => 'deputy-head']);
        $user->roles()->attach($role->id);
        User::forgetPermissionCache($user->id);

        $this->deputy->update(['head_user_id' => $user->id]);

        return $user;
    }

    private function subordinate(?Unit $unit = null): User
    {
        return User::factory()->create([
            'unit_id' => ($unit ?? $this->unit)->id,
            'position_id' => $this->position->id,
            'contract_type_id' => $this->contractType->id,
        ]);
    }

    public function test_a_deputy_can_register_normal_hours_for_a_subordinate_unit(): void
    {
        // هسته‌ی خواسته: واحد مدیر ندارد، ولی کار نباید زمین بماند.
        $head = $this->deputyHead();
        $person = $this->subordinate();

        $this->actingAs($head)
            ->postJson(route('admin.overtime.periods.normal.store', $person), [
                'normal_hours' => 30,
                'jalali_year' => $this->year,
                'jalali_month' => $this->month,
            ])
            ->assertOk();

        $this->assertSame('30.00', (string) $person->overtimePeriods()->first()->normal_hours);
    }

    public function test_a_deputy_can_register_in_bulk_across_their_whole_deputy(): void
    {
        $head = $this->deputyHead();

        // دو واحد مختلف زیر همین معاونت
        $otherUnit = Unit::create(['deputy_id' => $this->deputy->id, 'title' => 'واحد مکانیک', 'is_active' => true]);
        $a = $this->subordinate();
        $b = $this->subordinate($otherUnit);

        $this->actingAs($head)
            ->postJson(route('admin.overtime.periods.bulk-normal'), [
                'normal_hours' => 20,
                'jalali_year' => $this->year,
                'jalali_month' => $this->month,
            ])
            ->assertOk()
            ->assertJson(['applied' => 2]);

        foreach ([$a, $b] as $person) {
            $this->assertSame('20.00', (string) $person->overtimePeriods()->first()->normal_hours);
        }
    }

    public function test_a_deputy_can_submit_what_they_registered(): void
    {
        // بدون این، رکوردی که معاون ثبت کرده در وضعیت draft گیر می‌کرد.
        $head = $this->deputyHead();
        $person = $this->subordinate();

        OvertimePeriod::create([
            'personnel_id' => $person->id,
            'jalali_year' => $this->year, 'jalali_month' => $this->month,
            'normal_hours' => '25',
        ]);

        $this->actingAs($head)
            ->postJson(route('admin.overtime.periods.bulk-submit'), [
                'jalali_year' => $this->year, 'jalali_month' => $this->month,
            ])
            ->assertOk()
            ->assertJson(['submitted' => 1]);

        $this->assertSame(
            OvertimePeriodStatus::ManagerSubmitted,
            $person->overtimePeriods()->first()->status
        );
    }

    public function test_a_deputy_cannot_register_outside_their_own_deputy(): void
    {
        $head = $this->deputyHead();

        $otherDeputy = Deputy::create(['title' => 'معاونت دیگر', 'is_active' => true]);
        $otherUnit = Unit::create(['deputy_id' => $otherDeputy->id, 'title' => 'واحد بیگانه', 'is_active' => true]);
        $outsider = $this->subordinate($otherUnit);

        $this->actingAs($head)
            ->postJson(route('admin.overtime.periods.normal.store', $outsider), [
                'normal_hours' => 10,
                'jalali_year' => $this->year, 'jalali_month' => $this->month,
            ])
            ->assertForbidden();
    }

    public function test_a_deputy_is_still_bound_by_the_group_ceiling(): void
    {
        // دسترسی معاونت میان‌بری برای عبور از سقف نیست.
        $head = $this->deputyHead();
        $this->subordinate();
        $this->subordinate();

        $response = $this->actingAs($head)
            ->postJson(route('admin.overtime.periods.bulk-normal'), [
                'normal_hours' => 100,   // سقف سمت ۹۰ است
                'jalali_year' => $this->year, 'jalali_month' => $this->month,
            ])
            ->assertOk();

        $this->assertSame(1, $response->json('applied'));
        $this->assertCount(1, $response->json('skipped'));
    }

    public function test_the_listing_covers_both_the_deputy_and_any_unit_they_manage(): void
    {
        // سناریوی واقعی: معاونِ معاونت الف که مدیر واحدی زیر معاونت ب هم هست.
        $head = $this->deputyHead();
        $inDeputy = $this->subordinate();

        $otherDeputy = Deputy::create(['title' => 'معاونت ب', 'is_active' => true]);
        $managedUnit = Unit::create([
            'deputy_id' => $otherDeputy->id, 'title' => 'واحد تحت مدیریت',
            'manager_user_id' => $head->id, 'is_active' => true,
        ]);
        $inUnit = $this->subordinate($managedUnit);

        $response = $this->actingAs($head)
            ->get(route('admin.overtime.periods.index'))
            ->assertOk();

        $ids = $response->viewData('personnel')->pluck('id');

        $this->assertTrue($ids->contains($inDeputy->id), 'پرسنل معاونت باید دیده شوند.');
        $this->assertTrue($ids->contains($inUnit->id), 'پرسنل واحدِ تحت مدیریت هم باید دیده شوند.');
    }
}
