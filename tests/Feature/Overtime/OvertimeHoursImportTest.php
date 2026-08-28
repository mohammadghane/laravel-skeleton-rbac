<?php

namespace Tests\Feature\Overtime;

use App\Imports\OvertimeHoursImport;
use App\Models\OvertimePeriod;
use App\Models\User;
use App\Services\OvertimePeriodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * تست ایمپورت اکسل «کسر کار» و «ماموریت» - بند ۴.۲ سند که این دو را «فقط از طریق فایل
 * اکسل» تعریف کرده است. هر پارامتر فرم و مسیر مستقل خودش را دارد.
 *
 * ساختار فایل: کد پرسنلی | نام | نام خانوادگی | ساعت
 *
 * نکته‌ای که این تست قفل می‌کند: پکیج Laravel Excel با WithHeadingRow عنوان ستون‌های
 * فارسی را با Str::slug به اسلاگ اسکی تبدیل می‌کند («کد پرسنلی» => `kd_prsnly`).
 */
class OvertimeHoursImportTest extends TestCase
{
    use RefreshDatabase;

    private const YEAR = 1405;
    private const MONTH = 5;

    private function import(string $type, array $rows): OvertimeHoursImport
    {
        $import = new OvertimeHoursImport($type, self::YEAR, self::MONTH, app(OvertimePeriodService::class));

        // شبیه‌سازی همان چیزی که Laravel Excel پس از اعمال WithHeadingRow پاس می‌دهد.
        $import->collection(new Collection(array_map(fn ($r) => new Collection($r), $rows)));

        return $import;
    }

    private function periodOf(User $personnel): OvertimePeriod
    {
        return OvertimePeriod::where('personnel_id', $personnel->id)
            ->where('jalali_year', self::YEAR)->where('jalali_month', self::MONTH)
            ->firstOrFail();
    }

    public function test_it_imports_deduction_hours_from_the_four_column_file(): void
    {
        $personnel = User::factory()->create(['username' => '1024']);

        $import = $this->import(OvertimeHoursImport::TYPE_DEDUCTION, [
            ['kd_prsnly' => '1024', 'nam' => 'نگار', 'nam_khanoadgy' => 'احمدی', 'saaat' => '4.5'],
        ]);

        $this->assertSame(1, $import->importedCount);
        $this->assertSame([], $import->errors);
        $this->assertSame('4.50', (string) $this->periodOf($personnel)->deduction_hours);
    }

    public function test_it_imports_mission_hours_into_a_different_column(): void
    {
        $personnel = User::factory()->create(['username' => '1037']);

        $this->import(OvertimeHoursImport::TYPE_MISSION, [
            ['kd_prsnly' => '1037', 'saaat' => '12'],
        ]);

        $period = $this->periodOf($personnel);
        $this->assertSame('12.00', (string) $period->mission_hours);
        $this->assertNull($period->deduction_hours, 'ایمپورت ماموریت نباید کسر کار را مقداردهی کند.');
    }

    public function test_the_two_imports_do_not_overwrite_each_other(): void
    {
        // مهم‌ترین دستاورد تفکیک دو فرم: بارگذاری فایل ماموریت نباید کسر کارِ قبلاً
        // ثبت‌شده را پاک کند.
        $personnel = User::factory()->create(['username' => '1050']);

        $this->import(OvertimeHoursImport::TYPE_DEDUCTION, [['kd_prsnly' => '1050', 'saaat' => '3']]);
        $this->import(OvertimeHoursImport::TYPE_MISSION, [['kd_prsnly' => '1050', 'saaat' => '8']]);

        $period = $this->periodOf($personnel);
        $this->assertSame('3.00', (string) $period->deduction_hours);
        $this->assertSame('8.00', (string) $period->mission_hours);
    }

    public function test_it_accepts_english_column_names_too(): void
    {
        $personnel = User::factory()->create(['username' => '1060']);

        $this->import(OvertimeHoursImport::TYPE_DEDUCTION, [
            ['username' => '1060', 'hours' => '2'],
        ]);

        $this->assertSame('2.00', (string) $this->periodOf($personnel)->deduction_hours);
    }

    public function test_it_matches_personnel_by_national_code_as_fallback(): void
    {
        $personnel = User::factory()->create(['username' => 'x9', 'national_code' => '0012345678']);

        $this->import(OvertimeHoursImport::TYPE_DEDUCTION, [
            ['kd_mly' => '0012345678', 'saaat' => '1.25'],
        ]);

        $this->assertSame('1.25', (string) $this->periodOf($personnel)->deduction_hours);
    }

    public function test_unknown_personnel_is_reported_without_blocking_the_rest(): void
    {
        $known = User::factory()->create(['username' => '1070']);

        $import = $this->import(OvertimeHoursImport::TYPE_DEDUCTION, [
            ['kd_prsnly' => 'ناشناس', 'saaat' => '5'],
            ['kd_prsnly' => '1070', 'saaat' => '6'],
        ]);

        $this->assertSame(1, $import->importedCount);
        $this->assertCount(1, $import->errors);
        $this->assertStringContainsString('ناشناس', $import->errors[0]);
        $this->assertSame('6.00', (string) $this->periodOf($known)->deduction_hours);
    }

    public function test_missing_hours_column_is_reported(): void
    {
        User::factory()->create(['username' => '1080']);

        $import = $this->import(OvertimeHoursImport::TYPE_DEDUCTION, [
            ['kd_prsnly' => '1080'],
        ]);

        $this->assertSame(0, $import->importedCount);
        $this->assertCount(1, $import->errors);
        $this->assertStringContainsString('ساعت', $import->errors[0]);
    }

    public function test_non_numeric_hours_is_rejected(): void
    {
        User::factory()->create(['username' => '1090']);

        $import = $this->import(OvertimeHoursImport::TYPE_DEDUCTION, [
            ['kd_prsnly' => '1090', 'saaat' => 'چهار'],
        ]);

        $this->assertSame(0, $import->importedCount);
        $this->assertCount(1, $import->errors);
    }

    public function test_row_without_identifier_is_reported(): void
    {
        $import = $this->import(OvertimeHoursImport::TYPE_DEDUCTION, [
            ['saaat' => '5'],
        ]);

        $this->assertSame(0, $import->importedCount);
        $this->assertCount(1, $import->errors);
    }

    public function test_importing_does_not_change_workflow_status_or_overtime_hours(): void
    {
        // این پارامترها «مستقل»‌اند: نباید گردش کار تایید یا ساعت اضافه‌کار را جابه‌جا کنند.
        $personnel = User::factory()->create(['username' => '1100']);

        $this->import(OvertimeHoursImport::TYPE_DEDUCTION, [['kd_prsnly' => '1100', 'saaat' => '3']]);

        $period = $this->periodOf($personnel);
        $this->assertSame('draft', $period->status->value);
        $this->assertSame('0.00', (string) $period->normal_hours);
    }
}
