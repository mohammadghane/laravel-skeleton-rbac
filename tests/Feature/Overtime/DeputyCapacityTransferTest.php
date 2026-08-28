<?php

namespace Tests\Feature\Overtime;

use App\Exceptions\OvertimeCeilingExceededException;
use App\Models\Deputy;
use App\Models\DeputyCapacityTransfer;
use App\Models\OvertimePeriod;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use App\Services\OvertimeCeilingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * تست واگذاری ظرفیت اضافه‌کار مازاد بین معاونت‌ها - تبصره‌ی اول بخش «پارامترهای وابسته
 * به سمت سازمانی» در بازنگری بند ۴.۲:
 *
 *   «یک معاونت در صورتی که از تمام ظرفیت اضافه کار مازاد خود استفاده نکند می‌تواند
 *    باقی‌مانده اضافه کار تا سقف معاونت خود را به معاونت دیگری واگذار کند»
 *
 * فرمول: ظرفیت مؤثر = ظرفیت پایه - واگذارشده + دریافت‌شده
 */
class DeputyCapacityTransferTest extends TestCase
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

    /** یک معاونت با $count پرسنلِ سمت داده‌شده می‌سازد. */
    private function makeDeputyWith(string $title, Position $position, int $count): array
    {
        $deputy = Deputy::create(['title' => $title, 'is_active' => true]);
        $unit = Unit::create(['deputy_id' => $deputy->id, 'title' => $title . ' - واحد', 'is_active' => true]);

        $personnel = collect(range(1, $count))->map(fn () => User::factory()->create([
            'unit_id' => $unit->id,
            'position_id' => $position->id,
        ]));

        return [$deputy, $personnel];
    }

    private function makePosition(float $managerCeiling, float $excessCeiling): Position
    {
        $position = Position::create(['title' => 'کارشناس', 'is_active' => true]);

        $position->ceilingVersions()->create([
            'jalali_year' => self::YEAR,
            'jalali_month' => self::MONTH,
            'manager_ceiling_per_person' => $managerCeiling,
            'ceiling_per_person' => $excessCeiling,
        ]);

        return $position;
    }

    private function transfer(Deputy $from, Deputy $to, string $hours): DeputyCapacityTransfer
    {
        return DeputyCapacityTransfer::create([
            'from_deputy_id' => $from->id,
            'to_deputy_id' => $to->id,
            'jalali_year' => self::YEAR,
            'jalali_month' => self::MONTH,
            'hours' => $hours,
        ]);
    }

    public function test_base_capacity_is_headcount_times_excess_ceiling(): void
    {
        $position = $this->makePosition(managerCeiling: 90, excessCeiling: 5);
        [$deputy] = $this->makeDeputyWith('معاونت الف', $position, 4);

        // ۴ کارشناس × ۵ ساعت = ۲۰
        $this->assertSame('20.00', $this->service->deputyBaseExcessCapacity($deputy->id, self::YEAR, self::MONTH));
    }

    public function test_granting_capacity_reduces_the_donor_ceiling(): void
    {
        $position = $this->makePosition(managerCeiling: 90, excessCeiling: 5);
        [$donor, $donorPersonnel] = $this->makeDeputyWith('معاونت الف', $position, 4); // ظرفیت ۲۰
        [$receiver] = $this->makeDeputyWith('معاونت ب', $position, 4);

        $this->transfer($donor, $receiver, '8'); // ۸ ساعت واگذار شد => ظرفیت مؤثر ۱۲

        // تا ۱۲ ساعت مجاز است
        $this->service->assertExcessWithinCeiling($donorPersonnel->first(), self::YEAR, self::MONTH, '12');

        // ۱۳ ساعت باید رد شود (با اینکه سقف پایه‌اش ۲۰ بود)
        $this->expectException(OvertimeCeilingExceededException::class);
        $this->service->assertExcessWithinCeiling($donorPersonnel->first(), self::YEAR, self::MONTH, '13');
    }

    public function test_receiving_capacity_raises_the_recipient_ceiling(): void
    {
        $position = $this->makePosition(managerCeiling: 90, excessCeiling: 5);
        [$donor] = $this->makeDeputyWith('معاونت الف', $position, 4);
        [$receiver, $receiverPersonnel] = $this->makeDeputyWith('معاونت ب', $position, 2); // ظرفیت پایه ۱۰

        $this->transfer($donor, $receiver, '8'); // => ظرفیت مؤثر ۱۸

        // بیش از سقف پایه‌ی خودش (۱۰) ولی داخل ظرفیت مؤثر (۱۸)
        $this->service->assertExcessWithinCeiling($receiverPersonnel->first(), self::YEAR, self::MONTH, '18');

        $this->assertSame('18.00', bcadd(
            $this->service->deputyBaseExcessCapacity($receiver->id, self::YEAR, self::MONTH),
            DeputyCapacityTransfer::netFor($receiver->id, self::YEAR, self::MONTH),
            2
        ));
    }

    public function test_net_transfer_accounts_for_both_directions(): void
    {
        $position = $this->makePosition(managerCeiling: 90, excessCeiling: 5);
        [$a] = $this->makeDeputyWith('معاونت الف', $position, 4);
        [$b] = $this->makeDeputyWith('معاونت ب', $position, 4);

        $this->transfer($a, $b, '10'); // الف => ب
        $this->transfer($b, $a, '4');  // ب => الف

        // خالص برای الف: دریافت ۴ - واگذاری ۱۰ = -۶
        $this->assertSame('-6.00', DeputyCapacityTransfer::netFor($a->id, self::YEAR, self::MONTH));
        // خالص برای ب: دریافت ۱۰ - واگذاری ۴ = +۶
        $this->assertSame('6.00', DeputyCapacityTransfer::netFor($b->id, self::YEAR, self::MONTH));
    }

    public function test_remaining_capacity_reflects_usage_and_transfers(): void
    {
        $position = $this->makePosition(managerCeiling: 90, excessCeiling: 5);
        [$deputy, $personnel] = $this->makeDeputyWith('معاونت الف', $position, 4); // ظرفیت ۲۰
        [$other] = $this->makeDeputyWith('معاونت ب', $position, 2);

        // ۶ ساعت مصرف شده
        OvertimePeriod::create([
            'personnel_id' => $personnel->first()->id,
            'jalali_year' => self::YEAR, 'jalali_month' => self::MONTH,
            'excess_hours' => '6',
        ]);

        $this->assertSame('14.00', $this->service->deputyRemainingExcessCapacity($deputy->id, self::YEAR, self::MONTH));

        // ۴ ساعت واگذار می‌کند => باقی‌مانده ۱۰
        $this->transfer($deputy, $other, '4');

        $this->assertSame('10.00', $this->service->deputyRemainingExcessCapacity($deputy->id, self::YEAR, self::MONTH));
    }

    public function test_remaining_capacity_never_goes_negative(): void
    {
        $position = $this->makePosition(managerCeiling: 90, excessCeiling: 5);
        [$deputy, $personnel] = $this->makeDeputyWith('معاونت الف', $position, 2); // ظرفیت ۱۰
        [$other] = $this->makeDeputyWith('معاونت ب', $position, 2);

        OvertimePeriod::create([
            'personnel_id' => $personnel->first()->id,
            'jalali_year' => self::YEAR, 'jalali_month' => self::MONTH,
            'excess_hours' => '10',
        ]);

        $this->transfer($deputy, $other, '5'); // ظرفیت مؤثر ۵، مصرف ۱۰ => منفی

        $this->assertSame('0.00', $this->service->deputyRemainingExcessCapacity($deputy->id, self::YEAR, self::MONTH));
    }

    public function test_transfers_are_scoped_to_their_month(): void
    {
        // واگذاری ماه ۵ نباید روی ظرفیت ماه ۶ اثر بگذارد.
        $position = $this->makePosition(managerCeiling: 90, excessCeiling: 5);
        [$a] = $this->makeDeputyWith('معاونت الف', $position, 4);
        [$b] = $this->makeDeputyWith('معاونت ب', $position, 4);

        $this->transfer($a, $b, '8');

        $this->assertSame('-8.00', DeputyCapacityTransfer::netFor($a->id, self::YEAR, self::MONTH));
        $this->assertSame('0.00', DeputyCapacityTransfer::netFor($a->id, self::YEAR, 6));
    }
}
