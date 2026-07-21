<?php

namespace Database\Seeders;

use App\Models\ContractType;
use App\Models\Deputy;
use App\Models\Position;
use App\Models\Unit;
use App\Support\Jalali;
use Illuminate\Database\Seeder;

/**
 * داده‌ی اولیه‌ی چارت سازمانی و انواع قرارداد/سمت، طبق نمونه‌های ذکرشده در شرح خدمات.
 * این Seeder بدون ceiling_version اجرا می‌شود - تعریف سقف هر ماه وظیفه‌ی مدیر منابع
 * انسانی از طریق پنل است، نه Seeder (چون ماهانه نسخه‌بندی می‌شود).
 *
 * اجرا: DatabaseSeeder را ببینید (باید این کلاس را call کند)، یا مستقیم:
 *   php artisan db:seed --class=Database\\Seeders\\OvertimeOrgSeeder
 */
class OvertimeOrgSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['رسمی', 'شرکتی (پیمانکاری)', 'حجمی', 'متصدی ایستگاه', 'کار معین', 'طرح'] as $title) {
            ContractType::firstOrCreate(['title' => $title]);
        }

        foreach (['کارشناس', 'مدیر واحد', 'معاون', 'مدیرعامل'] as $title) {
            Position::firstOrCreate(['title' => $title]);
        }

        $financeDeputy = Deputy::firstOrCreate(['title' => 'معاونت مالی و پشتیبانی']);
        Unit::firstOrCreate(['deputy_id' => $financeDeputy->id, 'title' => 'مدیریت مالی']);
        Unit::firstOrCreate(['deputy_id' => $financeDeputy->id, 'title' => 'مدیریت پشتیبانی و خدمات']);

        $hrDeputy = Deputy::firstOrCreate(['title' => 'معاونت منابع انسانی']);
        Unit::firstOrCreate(['deputy_id' => $hrDeputy->id, 'title' => 'مدیریت منابع انسانی']);

        $itDeputy = Deputy::firstOrCreate(['title' => 'معاونت فناوری اطلاعات']);
        Unit::firstOrCreate(['deputy_id' => $itDeputy->id, 'title' => 'مدیریت زیرساخت و شبکه']);
        Unit::firstOrCreate(['deputy_id' => $itDeputy->id, 'title' => 'مدیریت توسعه نرم‌افزار']);

        // آیتم‌های ویژه‌ی اولیه‌ی ذکرشده در شرح خدمات
        foreach ([
            ['title' => 'ذیحسابی', 'submitter_role' => 'hr'],
            ['title' => 'ایثارگری', 'submitter_role' => 'hr'],
            ['title' => 'سرپرست خانوار', 'submitter_role' => 'hr'],
            ['title' => 'بازنشستگی', 'submitter_role' => 'hr'],
            ['title' => 'رفاهی', 'submitter_role' => 'unit_manager'],
        ] as $item) {
            \App\Models\SpecialItem::firstOrCreate(
                ['title' => $item['title']],
                ['submitter_role' => $item['submitter_role'], 'is_variable_per_personnel' => true, 'default_value' => 0]
            );
        }
    }
}
