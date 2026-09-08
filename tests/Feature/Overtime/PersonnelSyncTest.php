<?php

namespace Tests\Feature\Overtime;

use App\Models\ContractType;
use App\Models\Deputy;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use App\Services\PersonnelSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * تست همگام‌سازی پرسنل و چارت سازمانی از وب‌سرویس پرسنلی.
 *
 * ساختار رکورد مبدأ:
 *   PersonnelCode | FirstName | LastName | PostType/PostName
 *   | DepartmentType/DepartmentName | EmploymentType/EmploymentName | ManagerPersonnelCode
 */
class PersonnelSyncTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PersonnelSyncService
    {
        return app(PersonnelSyncService::class);
    }

    private function record(array $overrides = []): array
    {
        return array_merge([
            'PersonnelCode' => 52308,
            'FirstName' => 'حمیدرضا',
            'LastName' => 'رکنی',
            'PostType' => '5',
            'PostName' => 'کارشناس',
            'DepartmentType' => '2',
            'DepartmentName' => 'معاونت حفاظت و بهره برداری',
            'EmploymentType' => '1',
            'EmploymentName' => 'رسمی-پیمانی',
            'ManagerPersonnelCode' => 52155,
        ], $overrides);
    }

    /** یک مدیر و زیردستش - حداقل داده برای ساخت چارت. */
    private function managerAndSubordinate(): array
    {
        return [
            $this->record([
                'PersonnelCode' => 52155, 'FirstName' => 'ابوذر', 'LastName' => 'قادری',
                'PostType' => '3', 'PostName' => 'مدیر', 'ManagerPersonnelCode' => 5211,
            ]),
            $this->record(),
        ];
    }

    public function test_it_creates_contract_types_positions_and_personnel(): void
    {
        $result = $this->service()->sync([$this->record()]);

        $this->assertSame(1, $result['fetched']);
        $this->assertSame([], $result['errors']);

        $this->assertSame('رسمی-پیمانی', ContractType::where('external_code', 1)->value('title'));
        $this->assertNotNull(Position::where('title', 'کارشناس')->first());

        $user = User::where('personnel_code', '52308')->firstOrFail();
        $this->assertSame('حمیدرضا', $user->name);
        $this->assertSame('رکنی', $user->family);
        $this->assertNotNull($user->position_id);
        $this->assertNotNull($user->contract_type_id);
    }

    public function test_it_creates_the_deputy_from_department(): void
    {
        $this->service()->sync([$this->record()]);

        $deputy = Deputy::where('title', 'معاونت حفاظت و بهره برداری')->firstOrFail();
        $this->assertSame($deputy->id, User::where('personnel_code', '52308')->value('deputy_id'));
    }

    public function test_it_builds_units_from_the_manager_chain(): void
    {
        // مهم‌ترین قابلیت ساختار جدید: بدون این، پرسنل بدون مدیر می‌مانند و هیچ‌کس
        // نمی‌تواند برایشان اضافه‌کار ثبت کند.
        $this->service()->sync($this->managerAndSubordinate());

        $manager = User::where('personnel_code', '52155')->firstOrFail();
        $subordinate = User::where('personnel_code', '52308')->firstOrFail();

        $unit = Unit::where('manager_user_id', $manager->id)->firstOrFail();

        $this->assertSame($unit->id, $subordinate->unit_id, 'زیردست باید به واحد مدیرش وصل شود.');
        $this->assertNull($subordinate->deputy_id, 'پرسنلِ دارای واحد نباید مستقیمِ معاونت هم باشد.');
        $this->assertTrue($manager->isUnitManager());
    }

    public function test_the_deputy_head_is_taken_from_the_manager_chain(): void
    {
        // معاون در DepartmentType دفتر مدیرعامل ثبت شده، پس باید از روی زیردستانش
        // شناسایی شود نه از DepartmentType خودش.
        $this->service()->sync([
            $this->record([
                'PersonnelCode' => 5211, 'FirstName' => 'الیاس', 'LastName' => 'سلاجقه',
                'PostType' => '2', 'PostName' => 'معاون',
                'DepartmentType' => '1', 'DepartmentName' => 'رئیس هیئت مدیره و مدیرعامل',
                'ManagerPersonnelCode' => 5254,
            ]),
            $this->record(['ManagerPersonnelCode' => 5211]),
        ]);

        $deputy = Deputy::where('title', 'معاونت حفاظت و بهره برداری')->firstOrFail();
        $head = User::where('personnel_code', '5211')->firstOrFail();

        $this->assertSame($head->id, $deputy->head_user_id);
        $this->assertTrue($head->isDeputyHead());
    }

    public function test_a_person_who_manages_themselves_does_not_create_a_unit(): void
    {
        // مدیرعامل مدیرِ خودش ثبت شده؛ نباید واحدی برای خودش بسازد.
        $this->service()->sync([
            $this->record(['PersonnelCode' => 5254, 'PostType' => '1', 'PostName' => 'مدیرعامل', 'ManagerPersonnelCode' => 5254]),
        ]);

        $this->assertSame(0, Unit::count());
    }

    public function test_one_position_is_shared_by_everyone_with_the_same_title(): void
    {
        // سمت واحد گروه‌بندی سقف است، پس همه‌ی «کارشناس»ها یک رکورد دارند.
        $this->service()->sync([
            $this->record(['PersonnelCode' => 1]),
            $this->record(['PersonnelCode' => 2]),
            $this->record(['PersonnelCode' => 3]),
        ]);

        $this->assertSame(1, Position::count());
        $this->assertSame(3, User::whereNotNull('position_id')->count());
    }

    public function test_it_normalizes_arabic_characters_and_extra_spaces(): void
    {
        $this->service()->sync([$this->record([
            'FirstName' => 'يحيي',
            'LastName' => 'رشيدي',
            'PostName' => 'كارشناس   منابع آب  ',
            'EmploymentName' => 'قراردادي كارگري',
            'EmploymentType' => '4',
        ])]);

        $user = User::where('personnel_code', '52308')->firstOrFail();
        $this->assertSame('یحیی', $user->name);
        $this->assertSame('رشیدی', $user->family);

        $this->assertSame(1, Position::where('title', 'کارشناس منابع آب')->count());
        $this->assertSame('قراردادی کارگری', ContractType::where('external_code', 4)->value('title'));
    }

    public function test_running_twice_does_not_duplicate_anything(): void
    {
        $records = $this->managerAndSubordinate();

        $this->service()->sync($records);
        $second = $this->service()->sync($records);

        $this->assertSame(2, User::count());
        $this->assertSame(1, Unit::count());
        $this->assertSame(1, Deputy::count());
        $this->assertSame(0, $second['stats']['personnel_created']);
        $this->assertSame(0, $second['stats']['units_created']);
    }

    public function test_it_preserves_login_fields_of_existing_personnel(): void
    {
        $existing = User::factory()->create([
            'personnel_code' => '52308',
            'username' => 'hamid',
            'email' => 'hamid@example.test',
            'cell' => '09121234567',
        ]);
        $originalPassword = $existing->password;

        $this->service()->sync([$this->record()]);

        $user = $existing->fresh();
        $this->assertSame('hamid', $user->username, 'نام کاربری نباید بازنویسی شود.');
        $this->assertSame('hamid@example.test', $user->email);
        $this->assertSame('09121234567', $user->cell);
        $this->assertSame($originalPassword, $user->password);
        // ولی نام و جایگاه از مبدأ به‌روز می‌شوند
        $this->assertSame('حمیدرضا', $user->name);
    }

    public function test_it_adopts_a_user_whose_username_equals_the_personnel_code(): void
    {
        $existing = User::factory()->create(['username' => '52308', 'personnel_code' => null]);

        $this->service()->sync([$this->record()]);

        $this->assertSame(1, User::count());
        $this->assertSame('52308', $existing->fresh()->personnel_code);
    }

    public function test_records_without_personnel_code_are_reported_and_skipped(): void
    {
        $result = $this->service()->sync([
            $this->record(['PersonnelCode' => '']),
            $this->record(['PersonnelCode' => 51999]),
        ]);

        $this->assertSame(1, $result['stats']['skipped']);
        $this->assertCount(1, $result['errors']);
        $this->assertSame(1, User::whereNotNull('personnel_code')->count());
    }

    public function test_it_fetches_from_the_web_service(): void
    {
        // سرویس دو درخواست می‌زند: اول توکن، بعد داده. fake عمومی («*») هر دو را
        // با یک پاسخ جواب می‌داد و مرحله‌ی توکن شکست می‌خورد.
        Http::fake([
            '*openid-connect/token' => Http::response(['access_token' => 'fake-token'], 200),
            '*' => Http::response([$this->record()], 200),
        ]);

        $result = $this->service()->syncFromUrl('http://example.test/api/getpersonels.json');

        $this->assertSame(1, $result['fetched']);
        $this->assertSame(1, $result['stats']['personnel_created']);
    }

    public function test_it_fails_loudly_when_the_service_is_unreachable(): void
    {
        // توکن سالم گرفته می‌شود ولی خودِ سرویسِ داده خطا می‌دهد - یعنی همان حالتی
        // که این تست باید بپوشاند (نه شکستِ مرحله‌ی احراز هویت).
        Http::fake([
            '*openid-connect/token' => Http::response(['access_token' => 'fake-token'], 200),
            '*' => Http::response('', 500),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->service()->syncFromUrl('http://example.test/api/getpersonels.json');
    }

    public function test_clean_text_helper(): void
    {
        $this->assertSame('یحیی', PersonnelSyncService::cleanText('يحيي'));
        $this->assertSame('کارشناس برنامه', PersonnelSyncService::cleanText('  كارشناس   برنامه  '));
        $this->assertNull(PersonnelSyncService::cleanText('   '));
        $this->assertNull(PersonnelSyncService::cleanText(null));
        $this->assertSame('123', PersonnelSyncService::cleanText('١٢٣'));
    }
}
