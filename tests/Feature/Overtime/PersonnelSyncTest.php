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
 * تست همگام‌سازی پرسنل از وب‌سرویس پرسنلی سازمان.
 *
 * ساختار رکورد مبدأ:
 *   EmpCode | FirstName | LastName | EmploymentTypeDesc | EmploymentTypeCode
 *           | PostTitle | PostID | PostCode
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
            'EmpCode' => '51228',
            'FirstName' => 'یحیی',
            'LastName' => 'رشیدی',
            'EmploymentTypeDesc' => 'رسمی',
            'EmploymentTypeCode' => 9,
            'PostTitle' => 'مدیریت هماهنگی آب ناحیه شمال',
            'PostID' => 1061,
            'PostCode' => '97-7',
        ], $overrides);
    }

    public function test_it_creates_contract_types_positions_and_personnel(): void
    {
        $result = $this->service()->sync([$this->record()]);

        $this->assertSame(1, $result['fetched']);
        $this->assertSame([], $result['errors']);

        $contractType = ContractType::where('external_code', 9)->firstOrFail();
        $this->assertSame('رسمی', $contractType->title);

        // سمت بر اساس «عنوان» ساخته می‌شود؛ PostID/PostCode روی خودِ پرسنل می‌نشینند.
        $position = Position::where('title', 'مدیریت هماهنگی آب ناحیه شمال')->firstOrFail();

        $user = User::where('personnel_code', '51228')->firstOrFail();
        $this->assertSame('یحیی', $user->name);
        $this->assertSame('رشیدی', $user->family);
        $this->assertSame($position->id, $user->position_id);
        $this->assertSame($contractType->id, $user->contract_type_id);
        $this->assertSame(1061, (int) $user->post_id);
        $this->assertSame('97-7', $user->post_code);
    }

    public function test_one_position_is_shared_by_everyone_with_the_same_title(): void
    {
        // مهم‌ترین اصلاح: در سیستم مبدأ PostID شناسه‌ی «جایگاه» است نه «عنوان شغلی»؛
        // چند نفر با عنوان یکسان PostID متفاوت دارند. اگر سمت بر اساس PostID ساخته شود،
        // هر نفر یک سمت جداگانه می‌گیرد و «سقف گروه = تعداد × سقف» (بند ۴.۳) بی‌معنا می‌شود.
        $this->service()->sync([
            $this->record(['EmpCode' => '1', 'PostID' => 1001, 'PostCode' => '10-1']),
            $this->record(['EmpCode' => '2', 'PostID' => 1002, 'PostCode' => '10-2']),
            $this->record(['EmpCode' => '3', 'PostID' => 1003, 'PostCode' => '10-3']),
        ]);

        $this->assertSame(1, Position::count(), 'سه نفر با عنوان یکسان باید یک سمت مشترک داشته باشند.');
        $this->assertSame(3, User::whereNotNull('position_id')->count());

        // ولی جایگاه هرکس جداگانه ذخیره شده است
        $this->assertSame([1001, 1002, 1003], User::orderBy('personnel_code')->pluck('post_id')->map(fn ($v) => (int) $v)->all());
    }

    public function test_it_normalizes_arabic_characters_and_extra_spaces(): void
    {
        // داده‌ی واقعی سرویس «ي» و «ك» عربی دارد و عنوان‌ها فاصله‌ی انتهایی دارند.
        $this->service()->sync([$this->record([
            'FirstName' => 'يحيي',
            'LastName' => 'رشيدي',
            'EmploymentTypeDesc' => 'قراردادي كارگري',
            'EmploymentTypeCode' => 4,
            'PostTitle' => 'كارشناس برنامه و بودجه   ',
        ])]);

        $user = User::where('personnel_code', '51228')->firstOrFail();
        $this->assertSame('یحیی', $user->name, 'ی عربی باید به فارسی تبدیل شود.');
        $this->assertSame('رشیدی', $user->family);

        $this->assertSame('قراردادی کارگری', ContractType::where('external_code', 4)->value('title'));
        $this->assertSame(1, Position::where('title', 'کارشناس برنامه و بودجه')->count());
    }

    public function test_running_twice_does_not_duplicate_anything(): void
    {
        $records = [
            $this->record(),
            $this->record([
                'EmpCode' => '51238', 'FirstName' => 'محمدعلی',
                'PostID' => 1062, 'PostCode' => '97-8', 'PostTitle' => 'کارشناس منابع آب',
            ]),
        ];

        $this->service()->sync($records);
        $second = $this->service()->sync($records);

        $this->assertSame(2, User::whereNotNull('personnel_code')->count());
        $this->assertSame(1, ContractType::count());
        $this->assertSame(2, Position::count(), 'دو عنوان متفاوت => دو سمت.');

        $this->assertSame(0, $second['stats']['personnel_created']);
        $this->assertSame(0, $second['stats']['positions_created']);
    }

    public function test_it_updates_titles_when_they_change_in_the_source(): void
    {
        $this->service()->sync([$this->record()]);

        $this->service()->sync([$this->record([
            'PostTitle' => 'مدیریت هماهنگی آب ناحیه جنوب',
            'EmploymentTypeDesc' => 'رسمی قطعی',
        ])]);

        // تطبیق بر اساس کد است، پس عنوان به‌روز می‌شود و رکورد تکراری ساخته نمی‌شود.
        // نوع قرارداد بر اساس کد تطبیق داده می‌شود، پس تغییر عنوان رکورد تکراری نمی‌سازد.
        $this->assertSame('رسمی قطعی', ContractType::where('external_code', 9)->value('title'));

        // سمت بر اساس عنوان است؛ تغییر عنوان در مبدأ یعنی یک سمت جدید. این رفتار درست
        // است چون «عنوان شغلی» خودش هویت سمت است و سقف روی همان تعریف می‌شود.
        $this->assertSame(2, Position::count());
        $this->assertSame(1, Position::where('title', 'مدیریت هماهنگی آب ناحیه جنوب')->count());
    }

    public function test_it_links_an_existing_manually_created_contract_type_instead_of_duplicating(): void
    {
        // نوع قراردادی که HR قبلاً دستی ساخته (بدون کد مبدأ) باید به کد وصل شود، نه اینکه
        // رکورد تکراری ساخته شود - وگرنه سقف‌های تعریف‌شده روی آن بی‌استفاده می‌ماندند.
        $contractType = ContractType::create(['title' => 'رسمی', 'is_active' => true]);
        $position = Position::create(['title' => 'مدیریت هماهنگی آب ناحیه شمال', 'is_active' => true]);

        $this->service()->sync([$this->record()]);

        $this->assertSame(1, ContractType::count());
        $this->assertSame(1, Position::count(), 'سمت هم‌عنوان باید همان رکورد موجود بماند.');
        $this->assertSame(9, (int) $contractType->fresh()->external_code);
        $this->assertSame($position->id, User::where('personnel_code', '51228')->value('position_id'));
    }

    public function test_it_preserves_org_chart_assignment_and_login_fields(): void
    {
        // سیستم مبدأ واحد/معاونت و اطلاعات ورود را ندارد؛ همگام‌سازی نباید آن‌ها را پاک کند.
        $deputy = Deputy::create(['title' => 'معاونت فناوری', 'is_active' => true]);
        $unit = Unit::create(['deputy_id' => $deputy->id, 'title' => 'واحد توسعه', 'is_active' => true]);

        $existing = User::factory()->create([
            'personnel_code' => '51228',
            'username' => 'yahya',
            'email' => 'yahya@example.test',
            'cell' => '09121234567',
            'unit_id' => $unit->id,
        ]);
        $originalPassword = $existing->password;

        $this->service()->sync([$this->record()]);

        $user = $existing->fresh();
        $this->assertSame($unit->id, $user->unit_id, 'تخصیص واحد باید حفظ شود.');
        $this->assertSame('yahya', $user->username, 'نام کاربری نباید بازنویسی شود.');
        $this->assertSame('yahya@example.test', $user->email);
        $this->assertSame('09121234567', $user->cell);
        $this->assertSame($originalPassword, $user->password, 'رمز عبور نباید تغییر کند.');
        // ولی نام و سمت از مبدأ به‌روز می‌شوند
        $this->assertSame('یحیی', $user->name);
        $this->assertNotNull($user->position_id);
    }

    public function test_it_adopts_a_user_whose_username_equals_the_employee_code(): void
    {
        // کاربری که قبلاً با کد پرسنلی به‌عنوان username ساخته شده باید همان رکورد
        // به‌روزرسانی شود، نه اینکه رکورد دوم ساخته شود.
        $existing = User::factory()->create(['username' => '51228', 'personnel_code' => null]);

        $this->service()->sync([$this->record()]);

        $this->assertSame(1, User::count());
        $this->assertSame('51228', $existing->fresh()->personnel_code);
    }

    public function test_records_without_employee_code_are_reported_and_skipped(): void
    {
        $result = $this->service()->sync([
            $this->record(['EmpCode' => '']),
            $this->record(['EmpCode' => '51999']),
        ]);

        $this->assertSame(1, $result['stats']['skipped']);
        $this->assertCount(1, $result['errors']);
        $this->assertSame(1, User::whereNotNull('personnel_code')->count());
    }

    public function test_it_fetches_from_the_web_service(): void
    {
        Http::fake([
            '*' => Http::response([$this->record()], 200),
        ]);

        $result = $this->service()->syncFromUrl('http://example.test/api/getpersonels.json');

        $this->assertSame(1, $result['fetched']);
        $this->assertSame(1, $result['stats']['personnel_created']);
    }

    public function test_it_fails_loudly_when_the_service_is_unreachable(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $this->expectException(\RuntimeException::class);
        $this->service()->syncFromUrl('http://example.test/api/getpersonels.json');
    }

    public function test_it_accepts_a_wrapped_array_payload(): void
    {
        // برخی سرویس‌ها آرایه را داخل یک کلید برمی‌گردانند.
        Http::fake([
            '*' => Http::response(['data' => [$this->record()]], 200),
        ]);

        $result = $this->service()->syncFromUrl('http://example.test/api/getpersonels.json');

        $this->assertSame(1, $result['stats']['personnel_created']);
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
