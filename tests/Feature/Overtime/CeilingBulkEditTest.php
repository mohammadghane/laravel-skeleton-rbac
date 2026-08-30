<?php

namespace Tests\Feature\Overtime;

use App\Models\ContractType;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * تست ویرایش گروهی سقف‌ها و رفتار «ارث‌بری» نسخه‌ها.
 *
 * قاعده‌ی کلیدی: کاربر مجبور نیست هر ماه سقف را دوباره ثبت کند. یک نسخه که برای ماه X
 * ثبت شود، برای همه‌ی ماه‌های بعدی هم معتبر است تا وقتی نسخه‌ی جدیدتری ثبت شود.
 */
class CeilingBulkEditTest extends TestCase
{
    use RefreshDatabase;

    private const YEAR = 1405;
    private const MONTH = 5;

    private function hrUser(): User
    {
        // علاوه بر دسترسی عملیاتی HR، دسترسیِ منوهای مربوطه هم لازم است چون مسیرها
        // اکنون با permission:menu/* محافظت می‌شوند.
        $keys = ['overtime/hr-manage', 'menu/contract-types', 'menu/positions'];
        $ids = collect($keys)->map(fn ($key) => Permission::firstOrCreate(['key' => $key], ['title' => $key])->id);

        $role = Role::firstOrCreate(['name' => 'hr']);
        $role->permissions()->syncWithoutDetaching($ids->all());

        $user = User::factory()->create();
        $user->roles()->attach($role->id);
        User::forgetPermissionCache($user->id);

        return $user;
    }

    private function contractType(string $title, ?array $ceiling = null): ContractType
    {
        $type = ContractType::create(['title' => $title, 'is_active' => true]);

        if ($ceiling) {
            $type->ceilingVersions()->create($ceiling + [
                'jalali_year' => self::YEAR,
                'jalali_month' => self::MONTH,
            ]);
        }

        return $type;
    }

    public function test_a_ceiling_set_once_stays_effective_for_later_months(): void
    {
        // هسته‌ی خواسته‌ی کارفرما: بدون ثبت مجدد، سقف ماه‌های بعد هم همین است.
        $type = $this->contractType('رسمی', [
            'ceiling_per_person' => 140, 'overall_ceiling' => 175, 'activation_day' => 20,
        ]);

        foreach ([[1405, 6], [1405, 12], [1406, 1], [1410, 7]] as [$year, $month]) {
            $effective = $type->ceilingFor($year, $month);

            $this->assertNotNull($effective, "سقف برای $year/$month باید از نسخه‌ی قبلی خوانده شود.");
            $this->assertSame('140.00', (string) $effective->ceiling_per_person);
            $this->assertSame(20, $effective->activation_day);
        }
    }

    public function test_a_newer_version_overrides_older_one_from_its_month_onward(): void
    {
        $type = $this->contractType('رسمی', [
            'ceiling_per_person' => 140, 'overall_ceiling' => 175, 'activation_day' => 20,
        ]);

        $type->ceilingVersions()->create([
            'jalali_year' => 1405, 'jalali_month' => 8,
            'ceiling_per_person' => 160, 'overall_ceiling' => 190, 'activation_day' => 25,
        ]);

        // پیش از ماه ۸ هنوز نسخه‌ی قدیمی معتبر است
        $this->assertSame('140.00', (string) $type->ceilingFor(1405, 7)->ceiling_per_person);
        // از ماه ۸ به بعد نسخه‌ی جدید
        $this->assertSame('160.00', (string) $type->ceilingFor(1405, 8)->ceiling_per_person);
        $this->assertSame('160.00', (string) $type->ceilingFor(1406, 3)->ceiling_per_person);
    }

    public function test_bulk_edit_updates_multiple_contract_types_at_once(): void
    {
        $formal = $this->contractType('رسمی', ['ceiling_per_person' => 140, 'activation_day' => 20]);
        $corporate = $this->contractType('شرکتی', ['ceiling_per_person' => 90, 'activation_day' => 20]);

        $this->actingAs($this->hrUser())
            ->postJson(route('admin.overtime.contract-types.ceilings.bulk'), [
                'contract_type_ids' => [$formal->id, $corporate->id],
                'jalali_year' => self::YEAR,
                'jalali_month' => self::MONTH,
                'activation_day' => 25,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        // فقط روز فعال‌سازی عوض شده؛ سقف‌ها باید دست‌نخورده بمانند.
        $this->assertSame(25, $formal->fresh()->ceilingFor(self::YEAR, self::MONTH)->activation_day);
        $this->assertSame(25, $corporate->fresh()->ceilingFor(self::YEAR, self::MONTH)->activation_day);
        $this->assertSame('140.00', (string) $formal->fresh()->ceilingFor(self::YEAR, self::MONTH)->ceiling_per_person);
        $this->assertSame('90.00', (string) $corporate->fresh()->ceilingFor(self::YEAR, self::MONTH)->ceiling_per_person);
    }

    public function test_bulk_edit_preserves_values_inherited_from_an_earlier_month(): void
    {
        // نوع قراردادی که سقفش را در ماه ۵ گرفته و حالا در ماه ۹ فقط روز فعال‌سازی عوض می‌شود:
        // سقف باید از نسخه‌ی ماه ۵ به نسخه‌ی جدید ماه ۹ منتقل شود، نه اینکه null شود.
        $type = $this->contractType('رسمی', [
            'ceiling_per_person' => 140, 'overall_ceiling' => 175, 'activation_day' => 20,
        ]);

        $this->actingAs($this->hrUser())
            ->postJson(route('admin.overtime.contract-types.ceilings.bulk'), [
                'contract_type_ids' => [$type->id],
                'jalali_year' => 1405, 'jalali_month' => 9,
                'activation_day' => 3,
            ])
            ->assertOk();

        $effective = $type->fresh()->ceilingFor(1405, 9);

        $this->assertSame(3, $effective->activation_day);
        $this->assertSame('140.00', (string) $effective->ceiling_per_person, 'سقف هر نفر باید حفظ شود.');
        $this->assertSame('175.00', (string) $effective->overall_ceiling, 'سقف کلی باید حفظ شود.');
        $this->assertSame(9, $effective->jalali_month, 'نسخه‌ی جدید برای ماه ۹ ساخته شده است.');
    }

    public function test_bulk_edit_can_clear_overall_ceiling_explicitly(): void
    {
        $type = $this->contractType('رسمی', [
            'ceiling_per_person' => 140, 'overall_ceiling' => 175, 'activation_day' => 20,
        ]);

        $this->actingAs($this->hrUser())
            ->postJson(route('admin.overtime.contract-types.ceilings.bulk'), [
                'contract_type_ids' => [$type->id],
                'jalali_year' => self::YEAR, 'jalali_month' => self::MONTH,
                'clear_overall_ceiling' => true,
            ])
            ->assertOk();

        $this->assertNull($type->fresh()->ceilingFor(self::YEAR, self::MONTH)->overall_ceiling);
    }

    public function test_bulk_edit_creates_a_ceiling_for_a_type_that_never_had_one(): void
    {
        // خواسته‌ی کارفرما: اگر برای نوع قراردادی که سقف نداشته، در ویرایش گروهی سقف
        // تعریف شود، باید اعمال شود - نه اینکه به‌بهانه‌ی نبودِ روز فعال‌سازی رد شود.
        $fresh = $this->contractType('طرح'); // بدون هیچ نسخه‌ی سقف

        $response = $this->actingAs($this->hrUser())
            ->postJson(route('admin.overtime.contract-types.ceilings.bulk'), [
                'contract_type_ids' => [$fresh->id],
                'jalali_year' => self::YEAR, 'jalali_month' => self::MONTH,
                'ceiling_per_person' => 120,
            ])
            ->assertOk();

        $this->assertContains('طرح', $response->json('updated'));
        $this->assertSame([], $response->json('skipped'));

        $effective = $fresh->fresh()->ceilingFor(self::YEAR, self::MONTH);
        $this->assertNotNull($effective);
        $this->assertSame('120.00', (string) $effective->ceiling_per_person);
        // روز فعال‌سازی وقتی داده نشده و نسخه‌ی قبلی هم نیست => پیش‌فرض ۱
        $this->assertSame(1, $effective->activation_day);
    }

    public function test_bulk_edit_applies_supplied_activation_day_to_a_brand_new_ceiling(): void
    {
        $fresh = $this->contractType('کار معین');

        $this->actingAs($this->hrUser())
            ->postJson(route('admin.overtime.contract-types.ceilings.bulk'), [
                'contract_type_ids' => [$fresh->id],
                'jalali_year' => self::YEAR, 'jalali_month' => self::MONTH,
                'ceiling_per_person' => 100,
                'overall_ceiling' => 130,
                'activation_day' => 18,
            ])
            ->assertOk();

        $effective = $fresh->fresh()->ceilingFor(self::YEAR, self::MONTH);
        $this->assertSame('100.00', (string) $effective->ceiling_per_person);
        $this->assertSame('130.00', (string) $effective->overall_ceiling);
        $this->assertSame(18, $effective->activation_day);
    }

    public function test_activation_day_only_edit_works_for_a_type_without_prior_ceiling(): void
    {
        // پس از بازنگری بند ۴.۲، «سقف در اختیار مدیر واحد» به سمت سازمانی منتقل شد و روی
        // نوع قرارداد فقط «سقف کلی» و «روز فعال‌سازی» معنا دارند. بنابراین ثبت صرفِ روز
        // فعال‌سازی برای نوع قراردادی که هیچ نسخه‌ای ندارد باید کار کند - نه اینکه رد شود.
        $never = $this->contractType('متصدی ایستگاه');

        $response = $this->actingAs($this->hrUser())
            ->postJson(route('admin.overtime.contract-types.ceilings.bulk'), [
                'contract_type_ids' => [$never->id],
                'jalali_year' => self::YEAR, 'jalali_month' => self::MONTH,
                'activation_day' => 10,
            ])
            ->assertOk();

        $this->assertContains('متصدی ایستگاه', $response->json('updated'));

        $effective = $never->fresh()->ceilingFor(self::YEAR, self::MONTH);
        $this->assertNotNull($effective);
        $this->assertSame(10, $effective->activation_day);
    }

    public function test_overall_ceiling_is_independent_of_the_legacy_per_person_column(): void
    {
        // ستون ceiling_per_person روی نوع قرارداد دیگر در محاسبات نقشی ندارد؛ پس سقف کلیِ
        // کمتر از آن هم باید پذیرفته شود (اعتبارسنجی قدیمی gte حذف شد).
        $type = $this->contractType('رسمی', ['ceiling_per_person' => 140, 'activation_day' => 20]);

        $this->actingAs($this->hrUser())
            ->postJson(route('admin.overtime.contract-types.ceilings.bulk'), [
                'contract_type_ids' => [$type->id],
                'jalali_year' => self::YEAR, 'jalali_month' => self::MONTH,
                'overall_ceiling' => 100,
            ])
            ->assertOk();

        $this->assertSame('100.00', (string) $type->fresh()->ceilingFor(self::YEAR, self::MONTH)->overall_ceiling);
    }

    public function test_bulk_edit_requires_at_least_one_value(): void
    {
        $type = $this->contractType('رسمی', ['ceiling_per_person' => 140, 'activation_day' => 20]);

        $this->actingAs($this->hrUser())
            ->postJson(route('admin.overtime.contract-types.ceilings.bulk'), [
                'contract_type_ids' => [$type->id],
                'jalali_year' => self::YEAR, 'jalali_month' => self::MONTH,
            ])
            ->assertStatus(422);
    }

    public function test_bulk_edit_is_denied_without_hr_permission(): void
    {
        $type = $this->contractType('رسمی', ['ceiling_per_person' => 140, 'activation_day' => 20]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.overtime.contract-types.ceilings.bulk'), [
                'contract_type_ids' => [$type->id],
                'jalali_year' => self::YEAR, 'jalali_month' => self::MONTH,
                'activation_day' => 25,
            ])
            ->assertForbidden();
    }

    public function test_position_bulk_edit_updates_excess_ceilings(): void
    {
        $expert = Position::create(['title' => 'کارشناس', 'is_active' => true]);
        $manager = Position::create(['title' => 'مدیر واحد', 'is_active' => true]);

        $this->actingAs($this->hrUser())
            ->postJson(route('admin.overtime.positions.ceilings.bulk'), [
                'position_ids' => [$expert->id, $manager->id],
                'jalali_year' => self::YEAR, 'jalali_month' => self::MONTH,
                'ceiling_per_person' => 7,
            ])
            ->assertOk();

        $this->assertSame('7.00', (string) $expert->fresh()->ceilingFor(self::YEAR, self::MONTH)->ceiling_per_person);
        $this->assertSame('7.00', (string) $manager->fresh()->ceilingFor(self::YEAR, self::MONTH)->ceiling_per_person);
        // و برای ماه‌های بعد هم معتبر است
        $this->assertSame('7.00', (string) $expert->fresh()->ceilingFor(1406, 2)->ceiling_per_person);
    }
}
