<?php

namespace Database\Seeders;

use App\Enums\OvertimePeriodStatus;
use App\Models\ContractType;
use App\Models\Deputy;
use App\Models\OvertimePeriod;
use App\Models\Position;
use App\Models\SpecialItem;
use App\Models\Unit;
use App\Models\User;
use App\Support\Jalali;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * داده‌ی نمونه‌ی «قابل نمایش» برای ماژول اضافه‌کار: یک معاونت با دو واحد، معاون و مدیران
 * واقعی، چند پرسنل با انواع قرارداد مختلف، سقف‌های ماه جاری و چند دوره در وضعیت‌های
 * مختلف گردش کار - تا داشبورد و صفحات ماژول با داده‌ی واقعی دیده و آزمایش شوند.
 *
 * برخلاف OvertimeOrgSeeder (که فقط چارت پایه را می‌سازد و برای پروداکشن هم بی‌خطر است)،
 * این Seeder کاربر و اضافه‌کار نمونه می‌سازد و فقط برای محیط توسعه/دمو است. عمداً در
 * DatabaseSeeder ثبت نشده؛ دستی اجرا کنید:
 *
 *   php artisan db:seed --class="Database\Seeders\OvertimeDemoSeeder"
 *
 * تمام کاربران نمونه با کلمه‌ی عبور `secret123` ساخته می‌شوند.
 */
class OvertimeDemoSeeder extends Seeder
{
    public function run(): void
    {
        $year = Jalali::currentYear();
        $month = Jalali::currentMonth();

        $formal = ContractType::firstOrCreate(['title' => 'رسمی']);
        $corporate = ContractType::firstOrCreate(['title' => 'شرکتی (پیمانکاری)']);

        // سقف‌های ماه جاری - مطابق مثال‌های عددی خودِ شرح خدمات (بند ۴.۲).
        $formal->ceilingVersions()->updateOrCreate(
            ['jalali_year' => $year, 'jalali_month' => $month],
            ['ceiling_per_person' => 140, 'overall_ceiling' => 175, 'activation_day' => 1]
        );
        $corporate->ceilingVersions()->updateOrCreate(
            ['jalali_year' => $year, 'jalali_month' => $month],
            ['ceiling_per_person' => 90, 'overall_ceiling' => 120, 'activation_day' => 1]
        );

        $expert = Position::firstOrCreate(['title' => 'کارشناس']);
        $managerPosition = Position::firstOrCreate(['title' => 'مدیر واحد']);
        $groupHead = Position::firstOrCreate(['title' => 'رئیس گروه']);
        $deputyPosition = Position::firstOrCreate(['title' => 'معاون']);

        // دو سقف مجزا روی هر سمت (بازنگری بند ۴.۲):
        //   manager_ceiling_per_person => سقف «عادی» در اختیار مدیر واحد
        //       مثال سند: کارمند و کارشناس ۹۰، رئیس گروه ۱۱۰، مدیر واحد ۱۲۰، معاونت‌ها ۱۷۵
        //   ceiling_per_person         => سقف «مازاد» در اختیار معاونت
        //       مثال سند: کارشناس ۵، مدیر ۱۰
        foreach ([
            [$expert, 90, 5],
            [$groupHead, 110, 8],
            [$managerPosition, 120, 10],
            [$deputyPosition, 175, 10],
        ] as [$position, $managerCeiling, $excessCeiling]) {
            $position->ceilingVersions()->updateOrCreate(
                ['jalali_year' => $year, 'jalali_month' => $month],
                ['manager_ceiling_per_person' => $managerCeiling, 'ceiling_per_person' => $excessCeiling]
            );
        }

        $deputy = Deputy::firstOrCreate(['title' => 'معاونت فناوری اطلاعات']);
        $devUnit = Unit::firstOrCreate(['deputy_id' => $deputy->id, 'title' => 'مدیریت توسعه نرم‌افزار']);
        $infraUnit = Unit::firstOrCreate(['deputy_id' => $deputy->id, 'title' => 'مدیریت زیرساخت و شبکه']);

        // معاون و مدیران واحد
        $deputyHead = $this->makeUser('معصومه', 'رضایی', 'deputy.it', $managerPosition, $formal, deputyId: $deputy->id);
        $deputy->update(['head_user_id' => $deputyHead->id]);

        $devManager = $this->makeUser('سارا', 'کریمی', 'manager.dev', $managerPosition, $formal, unitId: $devUnit->id);
        $devUnit->update(['manager_user_id' => $devManager->id]);

        $infraManager = $this->makeUser('حسین', 'مرادی', 'manager.infra', $managerPosition, $formal, unitId: $infraUnit->id);
        $infraUnit->update(['manager_user_id' => $infraManager->id]);

        // پرسنل واحد توسعه
        $dev1 = $this->makeUser('نگار', 'احمدی', 'dev.1', $expert, $formal, unitId: $devUnit->id);
        $dev2 = $this->makeUser('امیر', 'حسینی', 'dev.2', $expert, $formal, unitId: $devUnit->id);
        $dev3 = $this->makeUser('مریم', 'صادقی', 'dev.3', $expert, $corporate, unitId: $devUnit->id);

        // پرسنل واحد زیرساخت
        $infra1 = $this->makeUser('رضا', 'نوری', 'infra.1', $expert, $formal, unitId: $infraUnit->id);
        $infra2 = $this->makeUser('زهرا', 'قاسمی', 'infra.2', $expert, $corporate, unitId: $infraUnit->id);

        // دوره‌ها در وضعیت‌های مختلف گردش کار، تا همه‌ی حالت‌های UI دیده شود.
        $this->makePeriod($dev1, $year, $month, '130', '4', OvertimePeriodStatus::Finalized, specialHours: '45'); // از سقف کلی ۱۷۵ عبور می‌کند
        $this->makePeriod($dev2, $year, $month, '120', '5', OvertimePeriodStatus::DeputyApproved);
        $this->makePeriod($dev3, $year, $month, '80', '0', OvertimePeriodStatus::ManagerSubmitted);
        $this->makePeriod($infra1, $year, $month, '95', '0', OvertimePeriodStatus::Draft);
        // infra2 عمداً بدون دوره می‌ماند تا بخش «پرسنل بدون ثبت» داشبورد دیده شود.

        $this->command?->info('داده‌ی نمونه‌ی اضافه‌کار ساخته شد. کلمه‌ی عبور همه: secret123');
        $this->command?->info("معاون: deputy.it | مدیران: manager.dev, manager.infra");
    }

    private function makeUser(string $name, string $family, string $username, Position $position, ContractType $contractType, ?int $unitId = null, ?int $deputyId = null): User
    {
        return User::firstOrCreate(
            ['username' => $username],
            [
                'name' => $name,
                'family' => $family,
                'email' => $username . '@example.test',
                'password' => Hash::make('secret123'),
                'position_id' => $position->id,
                'contract_type_id' => $contractType->id,
                'unit_id' => $unitId,
                'deputy_id' => $deputyId,
            ]
        );
    }

    private function makePeriod(User $personnel, int $year, int $month, string $normal, string $excess, OvertimePeriodStatus $status, string $specialHours = '0'): void
    {
        $period = OvertimePeriod::updateOrCreate(
            ['personnel_id' => $personnel->id, 'jalali_year' => $year, 'jalali_month' => $month],
            [
                'normal_hours' => $normal,
                'excess_hours' => $excess,
                'status' => $status,
                'unit_id_snap' => $personnel->unit_id,
                'unit_title_snap' => $personnel->unit?->title,
                'deputy_id_snap' => $personnel->effectiveDeputyId(),
                'deputy_title_snap' => $personnel->deputy?->title ?? $personnel->unit?->deputy?->title,
                'position_id_snap' => $personnel->position_id,
                'position_title_snap' => $personnel->position?->title,
                'contract_type_id_snap' => $personnel->contract_type_id,
                'contract_type_title_snap' => $personnel->contractType?->title,
                'worked_days_count' => 30,
                'rial_coefficient' => 250000,
            ]
        );

        if (bccomp($specialHours, '0', 2) === 1) {
            $item = SpecialItem::firstOrCreate(['title' => 'ایثارگری'], ['submitter_role' => 'hr', 'default_value' => 0]);

            $period->specialItemLines()->updateOrCreate(
                ['special_item_id' => $item->id],
                ['title_snap' => $item->title, 'hours' => $specialHours, 'deputy_approved' => true]
            );
        }
    }
}
