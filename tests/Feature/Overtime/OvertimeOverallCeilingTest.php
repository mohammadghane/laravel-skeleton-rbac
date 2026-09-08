<?php

namespace Tests\Feature\Overtime;

use App\Models\ContractType;
use App\Models\OvertimePeriod;
use App\Models\SpecialItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * تست «سقف کلی قرارداد» - 
 *
 *   «مجموع اضافه کار یک پرسنل شامل اضافه کار در اختیار مدیر + اضافه کار در اختیار معاونت
 *    + مجموع آیتم های ویژه نباید از سقف مجاز کلی قرارداد پرسنل تجاوز کند و در صورتی که از
 *    عدد سقف تعیین شده بیشتر شود مقدار سقف مجاز برای ارسال به واحد مالی در نظر گرفته میشود.»
 *
 * طبق تصمیم پروژه، ساعت خام ثبت‌شده در DB دست‌نخورده می‌ماند (الزام ردیابی بند ۵) و
 * capping فقط روی عددی که به واحد مالی می‌رود اعمال می‌شود.
 */
class OvertimeOverallCeilingTest extends TestCase
{
    use RefreshDatabase;

    private const YEAR = 1405;
    private const MONTH = 5;

    private function makePeriod(?float $overallCeiling, string $normal, string $excess = '0'): OvertimePeriod
    {
        $type = ContractType::create(['title' => 'رسمی', 'is_active' => true]);
        $type->ceilingVersions()->create([
            'jalali_year' => self::YEAR,
            'jalali_month' => self::MONTH,
            'ceiling_per_person' => 140,
            'overall_ceiling' => $overallCeiling,
            'activation_day' => 1,
        ]);

        $personnel = User::factory()->create(['contract_type_id' => $type->id]);

        return OvertimePeriod::create([
            'personnel_id' => $personnel->id,
            'jalali_year' => self::YEAR,
            'jalali_month' => self::MONTH,
            'normal_hours' => $normal,
            'excess_hours' => $excess,
            'contract_type_id_snap' => $type->id,
        ]);
    }

    private function addSpecialItem(OvertimePeriod $period, string $title, string $hours): void
    {
        $item = SpecialItem::create(['title' => $title, 'is_active' => true]);

        $period->specialItemLines()->create([
            'special_item_id' => $item->id,
            'title_snap' => $title,
            'hours' => $hours,
            'deputy_approved' => true,
        ]);
    }

    public function test_total_under_ceiling_is_paid_in_full(): void
    {
        // مثال سند: سقف کلی قرارداد رسمی ۱۷۵ ساعت. مجموع ۱۵۰ => کامل پرداخت می‌شود.
        $period = $this->makePeriod(overallCeiling: 175, normal: '140', excess: '10');
        $period->load('specialItemLines');

        $this->assertSame('150.00', $period->totalHours());
        $this->assertSame('150.00', $period->payableHours());
        $this->assertFalse($period->exceedsOverallCeiling());
        $this->assertSame('0.00', $period->cappedHours());
    }

    public function test_total_over_ceiling_is_capped_for_finance(): void
    {
        // عادی ۱۴۰ + مازاد ۱۰ + آیتم ویژه ۳۰ = ۱۸۰ که از سقف کلی ۱۷۵ عبور می‌کند.
        // عدد ارسالی به واحد مالی باید دقیقاً ۱۷۵ باشد، نه ۱۸۰.
        $period = $this->makePeriod(overallCeiling: 175, normal: '140', excess: '10');
        $this->addSpecialItem($period, 'ایثارگری', '30');
        $period->load('specialItemLines');

        $this->assertSame('180.00', $period->totalHours(), 'مجموع خام باید دست‌نخورده بماند.');
        $this->assertTrue($period->exceedsOverallCeiling());
        $this->assertSame('175.00', $period->payableHours(), 'عدد ارسالی به مالی باید سقف کلی باشد.');
        $this->assertSame('5.00', $period->cappedHours());
    }

    public function test_raw_hours_stay_intact_in_database(): void
    {
        // الزام ردیابی (بند ۵): capping نباید داده‌ی ثبت‌شده را تغییر دهد.
        $period = $this->makePeriod(overallCeiling: 100, normal: '150');
        $period->load('specialItemLines');

        $period->payableHours();

        $this->assertSame('150.00', (string) $period->fresh()->normal_hours);
    }

    public function test_exactly_at_ceiling_is_not_treated_as_exceeding(): void
    {
        $period = $this->makePeriod(overallCeiling: 175, normal: '175');
        $period->load('specialItemLines');

        $this->assertFalse($period->exceedsOverallCeiling());
        $this->assertSame('175.00', $period->payableHours());
        $this->assertSame('0.00', $period->cappedHours());
    }

    public function test_no_overall_ceiling_defined_means_no_capping(): void
    {
        // سازگاری با نسخه‌های سقفِ از قبل ثبت‌شده که overall_ceiling ندارند.
        $period = $this->makePeriod(overallCeiling: null, normal: '300');
        $period->load('specialItemLines');

        $this->assertNull($period->overallCeiling());
        $this->assertSame('300.00', $period->payableHours());
        $this->assertFalse($period->exceedsOverallCeiling());
    }

    public function test_capping_uses_decimal_precision(): void
    {
        // بند ۵: بدون خطای رند کردن.
        $period = $this->makePeriod(overallCeiling: 175.25, normal: '175.30');
        $period->load('specialItemLines');

        $this->assertTrue($period->exceedsOverallCeiling());
        $this->assertSame('175.25', $period->payableHours());
        $this->assertSame('0.05', $period->cappedHours());
    }
}
