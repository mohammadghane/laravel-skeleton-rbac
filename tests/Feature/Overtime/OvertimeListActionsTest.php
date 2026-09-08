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
 * تست اقدام‌های صفحه‌ی فهرست ثبت اضافه‌کار: ثبت گروهی، ویرایش درون‌خطی و ارسال به
 * معاونت - همه بدون خروج از همان جدول.
 *
 * خواسته‌ی کارفرما: «مدیر بتواند به صورت گروهی یک عدد مشخص را به عنوان اضافه‌کار
 * همه‌ی پرسنل زیرمجموعه تعریف کند»، «ویرایش اضافه‌کار یک پرسنل به صورت inline در
 * همان جدول باشد» و «اکشن ارسال برای بررسی معاونت در همان صفحه‌ی لیست اضافه شود».
 *
 * نکته‌ی کلیدی این تست‌ها: ثبت گروهی نباید میان‌بری برای دورزدن قواعد باشد. همان
 * پنجره‌ی زمانی، وضعیت قابل‌ویرایش و سقف گروهی که در
 * ثبت تکی اعمال می‌شود، اینجا هم اعمال می‌گردد - با این تفاوت که به‌جای شکست کل
 * عملیات، موارد ناموفق با ذکر دلیل گزارش می‌شوند.
 *
 * چون پنجره‌ی ثبت فقط در «ماه جاری» باز است (Jalali::isWithinActivationWindow)،
 * همه‌ی تست‌ها روی ماه جاری با activation_day = 1 کار می‌کنند.
 */
class OvertimeListActionsTest extends TestCase
{
    use RefreshDatabase;

    private int $year;
    private int $month;

    private Unit $unit;
    private Position $position;
    private ContractType $contractType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->year = Jalali::currentYear();
        $this->month = Jalali::currentMonth();

        $deputy = Deputy::create(['title' => 'معاونت آزمون', 'is_active' => true]);
        $this->unit = Unit::create(['deputy_id' => $deputy->id, 'title' => 'واحد آزمون', 'is_active' => true]);

        // پنجره‌ی ثبت از روز ۱ باز است تا تست به روز اجرا وابسته نباشد.
        $this->contractType = ContractType::create(['title' => 'رسمی', 'is_active' => true]);
        $this->contractType->ceilingVersions()->create([
            'jalali_year' => $this->year,
            'jalali_month' => $this->month,
            'ceiling_per_person' => 200,
            'overall_ceiling' => 250,
            'activation_day' => 1,
        ]);

        $this->position = Position::create(['title' => 'کارشناس', 'is_active' => true]);
        $this->position->ceilingVersions()->create([
            'jalali_year' => $this->year,
            'jalali_month' => $this->month,
            'ceiling_per_person' => 90,
            'manager_ceiling_per_person' => 90,
        ]);
    }

    /** مدیر واحد با دسترسی منوی ثبت اضافه‌کار. */
    private function manager(): User
    {
        $keys = ['menu/overtime-entry'];
        $ids = collect($keys)->map(fn ($key) => Permission::firstOrCreate(['key' => $key], ['title' => $key])->id);

        $role = Role::firstOrCreate(['name' => 'unit-manager']);
        $role->permissions()->syncWithoutDetaching($ids->all());

        $user = User::factory()->create();
        $user->roles()->attach($role->id);
        User::forgetPermissionCache($user->id);

        $this->unit->update(['manager_user_id' => $user->id]);

        return $user;
    }

    private function subordinate(): User
    {
        return User::factory()->create([
            'unit_id' => $this->unit->id,
            'position_id' => $this->position->id,
            'contract_type_id' => $this->contractType->id,
        ]);
    }

    private function bulk(User $manager, array $payload)
    {
        return $this->actingAs($manager)->postJson(route('admin.overtime.periods.bulk-normal'), $payload + [
            'jalali_year' => $this->year,
            'jalali_month' => $this->month,
        ]);
    }

    public function test_manager_can_assign_one_number_to_every_subordinate(): void
    {
        $manager = $this->manager();
        $people = collect(range(1, 3))->map(fn () => $this->subordinate());

        $response = $this->bulk($manager, ['normal_hours' => 20]);

        $response->assertOk()->assertJson(['success' => true, 'applied' => 3]);

        foreach ($people as $person) {
            $this->assertDatabaseHas('overtime_periods', [
                'personnel_id' => $person->id,
                'jalali_year' => $this->year,
                'jalali_month' => $this->month,
                'normal_hours' => '20.00',
            ]);
        }
    }

    public function test_bulk_entry_can_be_limited_to_selected_personnel(): void
    {
        $manager = $this->manager();
        $chosen = $this->subordinate();
        $untouched = $this->subordinate();

        $response = $this->bulk($manager, [
            'normal_hours' => 15,
            'personnel_ids' => [$chosen->id],
        ]);

        $response->assertOk()->assertJson(['applied' => 1]);

        $this->assertDatabaseHas('overtime_periods', ['personnel_id' => $chosen->id, 'normal_hours' => '15.00']);
        $this->assertDatabaseMissing('overtime_periods', ['personnel_id' => $untouched->id]);
    }

    public function test_existing_values_are_preserved_unless_overwrite_is_requested(): void
    {
        // ثبت گروهی نباید عددی که مدیر قبلاً دستی تنظیم کرده را بی‌سروصدا پاک کند.
        $manager = $this->manager();
        $person = $this->subordinate();

        OvertimePeriod::create([
            'personnel_id' => $person->id,
            'jalali_year' => $this->year,
            'jalali_month' => $this->month,
            'normal_hours' => '42',
        ]);

        $response = $this->bulk($manager, ['normal_hours' => 10]);

        $response->assertOk()->assertJson(['applied' => 0]);
        $this->assertSame('42.00', (string) $person->overtimePeriods()->first()->normal_hours);

        $response = $this->bulk($manager, ['normal_hours' => 10, 'overwrite' => true]);

        $response->assertOk()->assertJson(['applied' => 1]);
        $this->assertSame('10.00', (string) $person->overtimePeriods()->first()->normal_hours);
    }

    public function test_bulk_entry_cannot_bypass_the_group_ceiling(): void
    {
        // سقف گروهی = تعداد نفرات × سقف هر نفر. با سقف ۹۰ برای «کارشناس» و
        // ۲ نفر، مجموع مجاز ۱۸۰ ساعت است؛ درخواست ۱۰۰ ساعت برای هر دو (۲۰۰) رد می‌شود.
        $manager = $this->manager();
        $this->subordinate();
        $this->subordinate();

        $response = $this->bulk($manager, ['normal_hours' => 100]);

        $response->assertOk();

        // نفر اول در سقف جا می‌شود، نفر دوم از سقف گروهی عبور می‌کند.
        $this->assertSame(1, $response->json('applied'));
        $this->assertCount(1, $response->json('skipped'));
        $this->assertStringContainsString('سقف', $response->json('skipped.0'));
    }

    public function test_locked_periods_are_skipped_not_overwritten(): void
    {
        // پس از ارسال به معاونت، مدیر دیگر حق ویرایش ندارد - حتی گروهی.
        $manager = $this->manager();
        $locked = $this->subordinate();
        $open = $this->subordinate();

        $period = OvertimePeriod::create([
            'personnel_id' => $locked->id,
            'jalali_year' => $this->year,
            'jalali_month' => $this->month,
            'normal_hours' => '30',
        ]);
        $period->transitionTo(OvertimePeriodStatus::ManagerSubmitted, $manager->id);

        $response = $this->bulk($manager, ['normal_hours' => 25, 'overwrite' => true]);

        $response->assertOk();
        $this->assertSame(1, $response->json('applied'));

        $this->assertSame('30.00', (string) $period->fresh()->normal_hours, 'دوره‌ی ارسال‌شده نباید تغییر کند.');
        $this->assertDatabaseHas('overtime_periods', ['personnel_id' => $open->id, 'normal_hours' => '25.00']);
    }

    public function test_a_user_who_manages_no_unit_cannot_use_bulk_entry(): void
    {
        $role = Role::firstOrCreate(['name' => 'unit-manager']);
        $role->permissions()->syncWithoutDetaching([
            Permission::firstOrCreate(['key' => 'menu/overtime-entry'], ['title' => 'menu/overtime-entry'])->id,
        ]);

        $stranger = User::factory()->create();
        $stranger->roles()->attach($role->id);
        User::forgetPermissionCache($stranger->id);

        $this->bulk($stranger, ['normal_hours' => 10])->assertForbidden();
    }

    /**
     * ویرایش درون‌خطی روی همان endpoint ثبت تکی کار می‌کند، اما جدول برای به‌روزرسانی
     * ستون‌های «جمع» و «قابل پرداخت» به اعداد محاسبه‌شده‌ی سرور نیاز دارد - وگرنه
     * مجبور می‌شد منطق سقف را در جاوااسکریپت بازنویسی کند.
     */
    public function test_inline_edit_returns_server_computed_columns(): void
    {
        $manager = $this->manager();
        $person = $this->subordinate();

        $response = $this->actingAs($manager)->postJson(
            route('admin.overtime.periods.normal.store', $person),
            ['normal_hours' => 30, 'jalali_year' => $this->year, 'jalali_month' => $this->month]
        );

        $response->assertOk()
            ->assertJsonStructure(['success', 'message', 'data', 'computed' => [
                'total', 'payable', 'capped', 'exceeds', 'status_label',
            ]]);

        $this->assertSame('30.00', $response->json('computed.total'));
        $this->assertSame('30.00', $response->json('computed.payable'));
        $this->assertFalse($response->json('computed.exceeds'));
    }

    public function test_inline_edit_reports_the_capped_amount_when_over_the_overall_ceiling(): void
    {
        // سقف کلی قرارداد ۲۵۰ است؛ با سقف سمت ۹۰ نمی‌توان از آن گذشت مگر
        // آنکه مازاد/ویژه اضافه شود. اینجا مازاد را مستقیم می‌گذاریم تا capping دیده شود.
        $manager = $this->manager();
        $person = $this->subordinate();

        OvertimePeriod::create([
            'personnel_id' => $person->id,
            'jalali_year' => $this->year,
            'jalali_month' => $this->month,
            'normal_hours' => '0',
            'excess_hours' => '240',
        ]);

        $response = $this->actingAs($manager)->postJson(
            route('admin.overtime.periods.normal.store', $person),
            ['normal_hours' => 80, 'jalali_year' => $this->year, 'jalali_month' => $this->month]
        );

        $response->assertOk();
        $this->assertSame('320.00', $response->json('computed.total'));
        $this->assertSame('250.00', $response->json('computed.payable'), 'عدد ارسالی به مالی باید سقف باشد.');
        $this->assertSame('70.00', $response->json('computed.capped'));
        $this->assertTrue($response->json('computed.exceeds'));
    }

    public function test_inline_edit_is_rejected_for_personnel_outside_the_managers_unit(): void
    {
        $manager = $this->manager();

        $otherUnit = Unit::create(['deputy_id' => $this->unit->deputy_id, 'title' => 'واحد دیگر', 'is_active' => true]);
        $outsider = User::factory()->create([
            'unit_id' => $otherUnit->id,
            'position_id' => $this->position->id,
            'contract_type_id' => $this->contractType->id,
        ]);

        $this->actingAs($manager)->postJson(
            route('admin.overtime.periods.normal.store', $outsider),
            ['normal_hours' => 10, 'jalali_year' => $this->year, 'jalali_month' => $this->month]
        )->assertForbidden();
    }

    /** ارسال به معاونت از همان صفحه‌ی فهرست. */
    private function submitToDeputy(User $manager, array $payload = [])
    {
        return $this->actingAs($manager)->postJson(route('admin.overtime.periods.bulk-submit'), $payload + [
            'jalali_year' => $this->year,
            'jalali_month' => $this->month,
        ]);
    }

    private function periodFor(User $person, string $hours = '20'): OvertimePeriod
    {
        return OvertimePeriod::create([
            'personnel_id' => $person->id,
            'jalali_year' => $this->year,
            'jalali_month' => $this->month,
            'normal_hours' => $hours,
        ]);
    }

    public function test_manager_can_submit_every_ready_period_from_the_list(): void
    {
        $manager = $this->manager();
        $people = collect(range(1, 3))->map(fn () => $this->subordinate());
        $people->each(fn (User $p) => $this->periodFor($p));

        $response = $this->submitToDeputy($manager);

        $response->assertOk()->assertJson(['success' => true, 'submitted' => 3]);

        foreach ($people as $person) {
            $period = $person->overtimePeriods()->first();
            $this->assertSame(OvertimePeriodStatus::ManagerSubmitted, $period->status);
            $this->assertSame($manager->id, $period->submitted_by);
            $this->assertNotNull($period->manager_locked_at);
        }
    }

    public function test_submitting_can_be_limited_to_selected_rows(): void
    {
        $manager = $this->manager();
        $chosen = $this->subordinate();
        $untouched = $this->subordinate();

        $this->periodFor($chosen);
        $stayingDraft = $this->periodFor($untouched);

        $response = $this->submitToDeputy($manager, ['personnel_ids' => [$chosen->id]]);

        $response->assertOk()->assertJson(['submitted' => 1]);

        $this->assertSame(OvertimePeriodStatus::ManagerSubmitted, $chosen->overtimePeriods()->first()->status);
        $this->assertSame(OvertimePeriodStatus::Draft, $stayingDraft->fresh()->status);
    }

    public function test_submitting_locks_the_period_against_further_manager_edits(): void
    {
        // هسته‌ی ارسال یعنی قفل. اگر این نشکند، مدیر می‌تواند پس از ارسال
        // عدد را عوض کند و معاونت چیزی متفاوت با آنچه تایید کرده ببیند.
        $manager = $this->manager();
        $person = $this->subordinate();
        $this->periodFor($person);

        $this->submitToDeputy($manager)->assertOk();

        $this->actingAs($manager)->postJson(
            route('admin.overtime.periods.normal.store', $person),
            ['normal_hours' => 55, 'jalali_year' => $this->year, 'jalali_month' => $this->month]
        )->assertStatus(422);

        $this->assertSame('20.00', (string) $person->overtimePeriods()->first()->normal_hours);
    }

    public function test_already_submitted_periods_are_reported_not_resubmitted(): void
    {
        $manager = $this->manager();
        $done = $this->subordinate();
        $ready = $this->subordinate();

        $this->periodFor($done)->transitionTo(OvertimePeriodStatus::ManagerSubmitted, $manager->id);
        $this->periodFor($ready);

        $response = $this->submitToDeputy($manager);

        $response->assertOk();
        $this->assertSame(1, $response->json('submitted'));
        $this->assertCount(1, $response->json('skipped'));
    }

    public function test_personnel_without_a_period_are_not_submitted_as_zero(): void
    {
        // ارسالِ صفر به معاونت یعنی «این فرد اضافه‌کار ندارد» که تصمیم مدیر است،
        // نه پیش‌فرضی که سیستم بی‌اجازه بسازد.
        $manager = $this->manager();
        $withPeriod = $this->subordinate();
        $withoutPeriod = $this->subordinate();

        $this->periodFor($withPeriod);

        $response = $this->submitToDeputy($manager);

        $response->assertOk()->assertJson(['submitted' => 1]);
        $this->assertDatabaseMissing('overtime_periods', ['personnel_id' => $withoutPeriod->id]);
    }

    public function test_submitting_never_reaches_another_managers_unit(): void
    {
        $manager = $this->manager();

        $otherUnit = Unit::create(['deputy_id' => $this->unit->deputy_id, 'title' => 'واحد دیگر', 'is_active' => true]);
        $outsider = User::factory()->create([
            'unit_id' => $otherUnit->id,
            'position_id' => $this->position->id,
            'contract_type_id' => $this->contractType->id,
        ]);
        $outsiderPeriod = $this->periodFor($outsider);

        $response = $this->submitToDeputy($manager, ['personnel_ids' => [$outsider->id]]);

        $response->assertOk()->assertJson(['submitted' => 0]);
        $this->assertSame(OvertimePeriodStatus::Draft, $outsiderPeriod->fresh()->status);
    }

    public function test_a_user_who_manages_no_unit_cannot_submit_in_bulk(): void
    {
        $role = Role::firstOrCreate(['name' => 'unit-manager']);
        $role->permissions()->syncWithoutDetaching([
            Permission::firstOrCreate(['key' => 'menu/overtime-entry'], ['title' => 'menu/overtime-entry'])->id,
        ]);

        $stranger = User::factory()->create();
        $stranger->roles()->attach($role->id);
        User::forgetPermissionCache($stranger->id);

        $this->submitToDeputy($stranger)->assertForbidden();
    }

    /**
     * باگی که کاربر گزارش کرد: وقتی عملیات گروهی **جزئی** موفق می‌شد، جدول به‌روز
     * نمی‌شد و کاربر مجبور بود صفحه را دستی رفرش کند. برای به‌روزرسانی درجا، پاسخ باید
     * بگوید دقیقاً کدام رکوردها موفق شدند و مقادیر تازه‌شان چیست.
     */
    public function test_partial_bulk_entry_reports_updated_rows_for_in_place_refresh(): void
    {
        $manager = $this->manager();
        $ok = $this->subordinate();
        $locked = $this->subordinate();

        $lockedPeriod = $this->periodFor($locked, '30');
        $lockedPeriod->transitionTo(OvertimePeriodStatus::ManagerSubmitted, $manager->id);

        $response = $this->bulk($manager, ['normal_hours' => 12, 'overwrite' => true]);

        $response->assertOk();
        $this->assertSame(1, $response->json('applied'));
        $this->assertCount(1, $response->json('skipped'), 'دوره‌ی قفل‌شده باید رد شود.');

        // با وجود موفقیتِ جزئی، سطر موفق باید داده‌ی تازه داشته باشد.
        $rows = $response->json('rows');
        $this->assertArrayHasKey((string) $ok->id, $rows);
        $this->assertArrayNotHasKey((string) $locked->id, $rows, 'سطر ردشده نباید داده‌ی به‌روز بگیرد.');

        $row = $rows[(string) $ok->id];
        $this->assertSame('12.00', $row['normal_hours']);
        $this->assertSame('12.00', $row['total']);
        $this->assertSame('draft', $row['status']);
        $this->assertTrue($row['editable']);
    }

    public function test_partial_bulk_submit_reports_locked_rows_for_in_place_refresh(): void
    {
        $manager = $this->manager();
        $ok = $this->subordinate();
        $already = $this->subordinate();

        $this->periodFor($ok);
        $this->periodFor($already)->transitionTo(OvertimePeriodStatus::ManagerSubmitted, $manager->id);

        $response = $this->submitToDeputy($manager);

        $response->assertOk();
        $this->assertSame(1, $response->json('submitted'));
        $this->assertCount(1, $response->json('skipped'));

        $rows = $response->json('rows');
        $this->assertArrayHasKey((string) $ok->id, $rows);

        // سطرِ ارسال‌شده باید قفل اعلام شود، وگرنه فیلد ویرایشِ بی‌فایده روی صفحه می‌ماند.
        $this->assertSame('manager_submitted', $rows[(string) $ok->id]['status']);
        $this->assertFalse($rows[(string) $ok->id]['editable']);
    }

    public function test_inline_edit_reports_status_so_the_row_can_be_updated(): void
    {
        $manager = $this->manager();
        $person = $this->subordinate();

        $response = $this->actingAs($manager)->postJson(
            route('admin.overtime.periods.normal.store', $person),
            ['normal_hours' => 25, 'jalali_year' => $this->year, 'jalali_month' => $this->month]
        );

        $response->assertOk();

        // اولین ثبت، سطر را از «ثبت نشده» به «پیش‌نویس» می‌برد و دکمه‌ی ارسال را
        // معنادار می‌کند؛ کلاینت برای هر دو به این مقادیر نیاز دارد.
        $this->assertSame('draft', $response->json('computed.status'));
        $this->assertTrue($response->json('computed.editable'));
    }

    public function test_bulk_entry_only_touches_the_managers_own_unit(): void
    {
        $manager = $this->manager();
        $mine = $this->subordinate();

        $otherUnit = Unit::create(['deputy_id' => $this->unit->deputy_id, 'title' => 'واحد دیگر', 'is_active' => true]);
        $outsider = User::factory()->create([
            'unit_id' => $otherUnit->id,
            'position_id' => $this->position->id,
            'contract_type_id' => $this->contractType->id,
        ]);

        // حتی وقتی id فرد بیرونی صراحتاً فرستاده شود، فیلتر واحد او را حذف می‌کند.
        $response = $this->bulk($manager, [
            'normal_hours' => 12,
            'personnel_ids' => [$mine->id, $outsider->id],
        ]);

        $response->assertOk()->assertJson(['applied' => 1]);
        $this->assertDatabaseMissing('overtime_periods', ['personnel_id' => $outsider->id]);
    }
}
