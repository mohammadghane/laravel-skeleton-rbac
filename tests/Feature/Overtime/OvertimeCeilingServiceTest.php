<?php

namespace Tests\Feature\Overtime;

use App\Exceptions\OvertimeCeilingExceededException;
use App\Models\ContractType;
use App\Models\Deputy;
use App\Models\OvertimePeriod;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use App\Services\OvertimeCeilingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * تست موتور کنترل سقف (بند ۴.۳ و ۴.۴ و بازنگری بند ۴.۲).
 *
 * دو قاعده‌ی محوری:
 *   ۱) سقف در سطح «گروه» اعمال می‌شود نه «فرد» - یک پرسنل می‌تواند بیشتر از سهم میانگین
 *      خود بگیرد، به شرطی که مجموع گروه از سقف عبور نکند (بند ۴.۴).
 *   ۲) مبنای گروه‌بندی سقف عادی **سمت سازمانی** است، نه نوع قرارداد (بازنگری بند ۴.۲،
 *      تبصره‌ی اول: «تعداد پرسنل × عدد مشخص شده بر اساس سمت»).
 */
class OvertimeCeilingServiceTest extends TestCase
{
    use RefreshDatabase;

    private OvertimeCeilingService $service;

    private const YEAR = 1405;
    private const MONTH = 5;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OvertimeCeilingService::class);
    }

    /** یک معاونت + یک واحد زیرمجموعه‌اش می‌سازد. */
    private function makeOrg(): array
    {
        $deputy = Deputy::create(['title' => 'معاونت فناوری اطلاعات', 'is_active' => true]);
        $unit = Unit::create(['deputy_id' => $deputy->id, 'title' => 'واحد توسعه نرم‌افزار', 'is_active' => true]);

        return [$deputy, $unit];
    }

    /**
     * یک سمت با سقف‌هایش می‌سازد.
     * $managerCeiling = سقف عادی (در اختیار مدیر واحد)، $excessCeiling = سقف مازاد (معاونت).
     */
    private function makePosition(string $title, ?float $managerCeiling = null, ?float $excessCeiling = null): Position
    {
        $position = Position::create(['title' => $title, 'is_active' => true]);

        if ($managerCeiling !== null || $excessCeiling !== null) {
            $position->ceilingVersions()->create([
                'jalali_year' => self::YEAR,
                'jalali_month' => self::MONTH,
                'manager_ceiling_per_person' => $managerCeiling,
                'ceiling_per_person' => $excessCeiling ?? 0,
            ]);
        }

        return $position;
    }

    private function makePersonnel(Unit $unit, Position $position, ?ContractType $type = null): User
    {
        return User::factory()->create([
            'unit_id' => $unit->id,
            'position_id' => $position->id,
            'contract_type_id' => $type?->id,
        ]);
    }

    private function recordNormal(User $personnel, string $hours): OvertimePeriod
    {
        return OvertimePeriod::create([
            'personnel_id' => $personnel->id,
            'jalali_year' => self::YEAR,
            'jalali_month' => self::MONTH,
            'normal_hours' => $hours,
            'unit_id_snap' => $personnel->unit_id,
        ]);
    }

    public function test_group_ceiling_is_headcount_times_position_ceiling(): void
    {
        // بند ۴.۳ قانون اول + تبصره‌ی اول بازنگری بند ۴.۲: سقف گروه = تعداد پرسنل × سقف سمت.
        // با ۳ کارشناس و سقف ۹۰، سقف کل گروه ۲۷۰ ساعت است.
        [, $unit] = $this->makeOrg();
        $expert = $this->makePosition('کارشناس', managerCeiling: 90);

        $first = $this->makePersonnel($unit, $expert);
        $this->makePersonnel($unit, $expert);
        $this->makePersonnel($unit, $expert);

        $this->service->assertNormalWithinCeiling($first, self::YEAR, self::MONTH, '270');

        $this->assertTrue(true, 'ثبت ۲۷۰ ساعت در گروه ۳ نفره با سقف ۹۰ نباید خطا بدهد.');
    }

    public function test_ceiling_differs_per_position_not_per_contract_type(): void
    {
        // مثال عددی سند: کارشناس ۹۰، رئیس گروه ۱۱۰. دو پرسنل با نوع قرارداد یکسان ولی
        // سمت متفاوت باید سقف‌های متفاوت داشته باشند.
        [, $unit] = $this->makeOrg();
        $formal = ContractType::create(['title' => 'رسمی', 'is_active' => true]);

        $expert = $this->makePosition('کارشناس', managerCeiling: 90);
        $groupHead = $this->makePosition('رئیس گروه', managerCeiling: 110);

        $expertPerson = $this->makePersonnel($unit, $expert, $formal);
        $headPerson = $this->makePersonnel($unit, $groupHead, $formal);

        // هرکدام دقیقاً تا سقف سمت خودش مجاز است
        $this->service->assertNormalWithinCeiling($expertPerson, self::YEAR, self::MONTH, '90');
        $this->service->assertNormalWithinCeiling($headPerson, self::YEAR, self::MONTH, '110');

        // ولی کارشناس نمی‌تواند تا سقف رئیس گروه برود
        $this->expectException(OvertimeCeilingExceededException::class);
        $this->service->assertNormalWithinCeiling($expertPerson, self::YEAR, self::MONTH, '110');
    }

    public function test_individual_may_exceed_their_own_average_share(): void
    {
        // بند ۴.۴: «ساعت اضافه‌کار یک پرسنل می‌تواند به صورت فردی از سقف تعریف‌شده برای او
        // تجاوز کند (عدم اعمال محدودیت سخت در سطح فرد)».
        [, $unit] = $this->makeOrg();
        $expert = $this->makePosition('کارشناس', managerCeiling: 90);

        $first = $this->makePersonnel($unit, $expert);
        $this->makePersonnel($unit, $expert);

        // سقف گروه = ۲ × ۹۰ = ۱۸۰. ثبت ۱۵۰ برای یک نفر (بیشتر از ۹۰) باید مجاز باشد.
        $this->service->assertNormalWithinCeiling($first, self::YEAR, self::MONTH, '150');

        $this->assertTrue(true);
    }

    public function test_group_total_over_ceiling_is_rejected(): void
    {
        // بند ۴.۴: مجموع گروه نباید از سقف عبور کند.
        [, $unit] = $this->makeOrg();
        $expert = $this->makePosition('کارشناس', managerCeiling: 90);

        $first = $this->makePersonnel($unit, $expert);
        $second = $this->makePersonnel($unit, $expert);

        // سقف گروه = ۲ × ۹۰ = ۱۸۰. نفر اول ۱۷۰ گرفته، نفر دوم ۲۰ می‌خواهد => ۱۹۰ > ۱۸۰
        $this->recordNormal($first, '170');

        $this->expectException(OvertimeCeilingExceededException::class);
        $this->service->assertNormalWithinCeiling($second, self::YEAR, self::MONTH, '20');
    }

    public function test_exactly_hitting_the_ceiling_is_allowed(): void
    {
        [, $unit] = $this->makeOrg();
        $expert = $this->makePosition('کارشناس', managerCeiling: 90);

        $first = $this->makePersonnel($unit, $expert);
        $second = $this->makePersonnel($unit, $expert);

        $this->recordNormal($first, '90');
        $this->service->assertNormalWithinCeiling($second, self::YEAR, self::MONTH, '90');

        $this->assertTrue(true, 'مجموع دقیقاً برابر سقف (۱۸۰) باید مجاز باشد.');
    }

    public function test_editing_a_record_excludes_its_own_previous_value(): void
    {
        [, $unit] = $this->makeOrg();
        $expert = $this->makePosition('کارشناس', managerCeiling: 100);

        $person = $this->makePersonnel($unit, $expert);
        $period = $this->recordNormal($person, '100'); // دقیقاً پر کرده

        // ویرایش همان رکورد به ۹۰ باید مجاز باشد (نه اینکه ۱۰۰ قبلی + ۹۰ جدید حساب شود)
        $this->service->assertNormalWithinCeiling($person, self::YEAR, self::MONTH, '90', excludePeriodId: $period->id);

        $this->assertTrue(true);
    }

    public function test_hours_are_compared_without_rounding_error(): void
    {
        // بند ۵ (دقت محاسباتی): محاسبات با bcmath انجام می‌شود.
        [, $unit] = $this->makeOrg();
        $expert = $this->makePosition('کارشناس', managerCeiling: 10.5);

        $person = $this->makePersonnel($unit, $expert);

        $this->service->assertNormalWithinCeiling($person, self::YEAR, self::MONTH, '10.50');

        $this->expectException(OvertimeCeilingExceededException::class);
        $this->service->assertNormalWithinCeiling($person, self::YEAR, self::MONTH, '10.51');
    }

    public function test_personnel_without_position_is_rejected(): void
    {
        [, $unit] = $this->makeOrg();
        $person = User::factory()->create(['unit_id' => $unit->id, 'position_id' => null]);

        $this->expectException(OvertimeCeilingExceededException::class);
        $this->service->assertNormalWithinCeiling($person, self::YEAR, self::MONTH, '10');
    }

    public function test_position_without_manager_ceiling_is_rejected(): void
    {
        // سمتی که فقط سقف مازاد دارد ولی سقف عادی (در اختیار مدیر) برایش تعریف نشده.
        [, $unit] = $this->makeOrg();
        $expert = $this->makePosition('کارشناس', managerCeiling: null, excessCeiling: 5);
        $person = $this->makePersonnel($unit, $expert);

        $this->expectException(OvertimeCeilingExceededException::class);
        $this->service->assertNormalWithinCeiling($person, self::YEAR, self::MONTH, '10');
    }

    public function test_deputy_level_ceiling_is_enforced_across_units(): void
    {
        // سطح ۲: حتی وقتی هر واحد جداگانه سقف خودش را رعایت کرده، مجموع کل معاونت
        // نباید از سقف عبور کند.
        $deputy = Deputy::create(['title' => 'معاونت مالی', 'is_active' => true]);
        $unitA = Unit::create(['deputy_id' => $deputy->id, 'title' => 'واحد الف', 'is_active' => true]);
        $unitB = Unit::create(['deputy_id' => $deputy->id, 'title' => 'واحد ب', 'is_active' => true]);

        $expert = $this->makePosition('کارشناس', managerCeiling: 100);

        $a = $this->makePersonnel($unitA, $expert);
        $b = $this->makePersonnel($unitB, $expert);

        // سقف معاونت = ۲ نفر × ۱۰۰ = ۲۰۰. نفر واحد الف ۱۰۰ گرفته.
        $this->recordNormal($a, '100');

        $this->expectException(OvertimeCeilingExceededException::class);
        $this->service->assertNormalWithinCeiling($b, self::YEAR, self::MONTH, '101');
    }

    public function test_excess_ceiling_is_based_on_position_headcount(): void
    {
        // بند ۴.۳ قانون دوم: سقف مازاد = تعداد پرسنل دارای آن سمت × سقف مازاد هر نفر.
        $deputy = Deputy::create(['title' => 'معاونت منابع انسانی', 'is_active' => true]);
        $unit = Unit::create(['deputy_id' => $deputy->id, 'title' => 'واحد جذب', 'is_active' => true]);

        $expert = $this->makePosition('کارشناس', managerCeiling: 90, excessCeiling: 5);

        $first = $this->makePersonnel($unit, $expert);
        $this->makePersonnel($unit, $expert);

        // سقف مازاد معاونت = ۲ کارشناس × ۵ = ۱۰ ساعت
        $this->service->assertExcessWithinCeiling($first, self::YEAR, self::MONTH, '10');

        $this->expectException(OvertimeCeilingExceededException::class);
        $this->service->assertExcessWithinCeiling($first, self::YEAR, self::MONTH, '11');
    }
}
