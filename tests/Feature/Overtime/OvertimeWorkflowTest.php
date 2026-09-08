<?php

namespace Tests\Feature\Overtime;

use App\Enums\OvertimePeriodStatus;
use App\Models\ContractType;
use App\Models\Deputy;
use App\Models\OvertimePeriod;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * تست گردش کار تایید اضافه‌کار و ثبت تاریخچه‌ی وضعیت (الزام ردیابی بند ۵).
 *
 * انگیزه‌ی اصلی: جدول تاریخچه در مهاجرت `overtime_status_history` (مفرد) ساخته شده ولی
 * Eloquent به‌طور پیش‌فرض آن را `overtime_status_histories` جمع می‌بندد. این ناهماهنگی باعث
 * می‌شد هر انتقال وضعیت خطای SQL بدهد. این تست‌ها آن را قفل می‌کنند.
 */
class OvertimeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private const YEAR = 1405;
    private const MONTH = 5;

    private function makePeriod(): OvertimePeriod
    {
        $deputy = Deputy::create(['title' => 'معاونت آزمون', 'is_active' => true]);
        $unit = Unit::create(['deputy_id' => $deputy->id, 'title' => 'واحد آزمون', 'is_active' => true]);
        $type = ContractType::create(['title' => 'رسمی', 'is_active' => true]);

        $personnel = User::factory()->create(['unit_id' => $unit->id, 'contract_type_id' => $type->id]);

        return OvertimePeriod::create([
            'personnel_id' => $personnel->id,
            'jalali_year' => self::YEAR,
            'jalali_month' => self::MONTH,
            'normal_hours' => '100',
        ]);
    }

    public function test_status_history_table_is_resolved_correctly(): void
    {
        // اگر $table روی مدل تنظیم نباشد، همین یک خط با خطای «table not found» می‌شکند.
        $period = $this->makePeriod();
        $actor = User::factory()->create();

        $period->transitionTo(OvertimePeriodStatus::ManagerSubmitted, $actor->id, 'ارسال برای بررسی');

        $this->assertDatabaseHas('overtime_status_history', [
            'overtime_period_id' => $period->id,
            'to_status' => 'manager_submitted',
            'actor_id' => $actor->id,
        ]);
    }

    public function test_transition_records_from_and_to_status(): void
    {
        $period = $this->makePeriod();
        $actor = User::factory()->create();

        $period->transitionTo(OvertimePeriodStatus::ManagerSubmitted, $actor->id);
        $period->transitionTo(OvertimePeriodStatus::DeputyApproved, $actor->id);

        $this->assertSame(OvertimePeriodStatus::DeputyApproved, $period->fresh()->status);

        // عمداً بر اساس id مرتب می‌کنیم نه created_at: هر دو انتقال در یک ثانیه ثبت می‌شوند
        // و ترتیبِ latest() در آن حالت قطعی نیست.
        $latest = $period->statusHistory()->reorder()->orderByDesc('id')->first();
        $this->assertSame('manager_submitted', $latest->from_status);
        $this->assertSame('deputy_approved', $latest->to_status);
    }

    public function test_full_approval_chain_reaches_finalized(): void
    {
        // مدیر واحد -> معاونت -> مدیرعامل -> نهایی‌شده
        $period = $this->makePeriod();
        $actor = User::factory()->create();

        $this->assertTrue($period->status->editableByManager());

        $period->transitionTo(OvertimePeriodStatus::ManagerSubmitted, $actor->id);
        $this->assertTrue($period->status->editableByDeputy());
        $this->assertFalse($period->status->editableByManager(), 'پس از ارسال، ویرایش برای مدیر قفل می‌شود.');

        $period->transitionTo(OvertimePeriodStatus::DeputyApproved, $actor->id);
        $this->assertTrue($period->status->editableByCeo());

        $period->transitionTo(OvertimePeriodStatus::CeoApproved, $actor->id);
        $period->transitionTo(OvertimePeriodStatus::Finalized, $actor->id);

        $this->assertSame(OvertimePeriodStatus::Finalized, $period->fresh()->status);
        $this->assertCount(4, $period->statusHistory()->get());
    }

    public function test_deputy_rejection_returns_control_to_manager(): void
    {
        // رد معاونت => گزارش برای اصلاح نزد مدیر واحد برمی‌گردد.
        $period = $this->makePeriod();
        $actor = User::factory()->create();

        $period->transitionTo(OvertimePeriodStatus::ManagerSubmitted, $actor->id);
        $period->transitionTo(OvertimePeriodStatus::DeputyRejected, $actor->id, 'اصلاح شود');

        $this->assertTrue($period->status->editableByManager(), 'پس از رد معاونت، مدیر واحد دوباره می‌تواند ویرایش کند.');
    }

    public function test_ceo_rejection_returns_control_to_deputy_not_manager(): void
    {
        // رد مدیرعامل مستقیم نزد معاونت برمی‌گردد، نه مدیر واحد.
        $period = $this->makePeriod();
        $actor = User::factory()->create();

        $period->transitionTo(OvertimePeriodStatus::ManagerSubmitted, $actor->id);
        $period->transitionTo(OvertimePeriodStatus::DeputyApproved, $actor->id);
        $period->transitionTo(OvertimePeriodStatus::CeoRejected, $actor->id);

        $this->assertTrue($period->status->editableByDeputy());
        $this->assertFalse($period->status->editableByManager());
    }
}
