<?php

namespace Tests\Feature;

use App\Models\Position;
use App\Models\User;
use App\Support\PersianText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * تست یکسان‌سازی متن فارسی (App\Support\PersianText) و میدل‌ور NormalizePersianInput.
 *
 * هدف: هر متنی که وارد سیستم می‌شود - از فرم، جست‌وجو، اکسل یا وب‌سرویس - باید با
 * حروف فارسی و ارقام لاتین ذخیره شود، وگرنه جست‌وجو نتیجه نمی‌دهد.
 */
class PersianTextNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public static function textProvider(): array
    {
        return [
            'ی عربی' => ['يحيي', 'یحیی'],
            'ک عربی' => ['كارشناس', 'کارشناس'],
            'ترکیب ی و ک' => ['مديريت كل', 'مدیریت کل'],
            'ارقام فارسی' => ['۰۹۱۲۱۲۳۴۵۶۷', '09121234567'],
            'ارقام عربی-هندی' => ['٠٩١٢', '0912'],
            'ارقام در متن' => ['کارشناس ۲', 'کارشناس 2'],
            'فاصله‌ی اضافی' => ['  کارشناس   منابع  آب  ', 'کارشناس منابع آب'],
            'ة به ه' => ['اداره كارخانة', 'اداره کارخانه'],
            'متن سالم بدون تغییر' => ['کارشناس منابع آب', 'کارشناس منابع آب'],
        ];
    }

    #[DataProvider('textProvider')]
    public function test_it_normalizes_text(string $input, string $expected): void
    {
        $this->assertSame($expected, PersianText::normalize($input));
    }

    public function test_for_search_also_replaces_zwnj(): void
    {
        // نیم‌فاصله فقط در جست‌وجو حذف می‌شود تا «سرپرست‌خانوار» و «سرپرست خانوار»
        // یکدیگر را پیدا کنند؛ در ذخیره‌سازی نیم‌فاصله حفظ می‌شود.
        $this->assertSame('سرپرست خانوار', PersianText::forSearch("سرپرست\u{200C}خانوار"));
        $this->assertSame("می\u{200C}رود", PersianText::normalize("می\u{200C}رود"));
    }

    public function test_null_and_empty_input(): void
    {
        $this->assertNull(PersianText::normalize(null));
        $this->assertSame('', PersianText::normalize('   '));
    }

    public function test_middleware_normalizes_submitted_form_values(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'يحيي',            // ی عربی
            'family' => 'رشيدي',
            'gender' => '1',
            'username' => 'test.user',
            'cell' => '۰۹۱۲۱۱۱۱۱۱۱',     // ارقام فارسی
            'email' => 'test.user@example.test',
            'password' => 'secret123',
        ])->assertOk();

        $user = User::where('username', 'test.user')->firstOrFail();
        $this->assertSame('یحیی', $user->name);
        $this->assertSame('رشیدی', $user->family);
        $this->assertSame('09121111111', $user->cell);
    }

    public function test_middleware_leaves_passwords_untouched(): void
    {
        // رمز عبور نباید یکسان‌سازی شود، وگرنه کاربر دیگر نمی‌تواند وارد شود.
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'تست',
            'gender' => '1',
            'username' => 'pass.user',
            'cell' => '09121111112',
            'email' => 'pass.user@example.test',
            'password' => 'كلمة۱۲۳عبور',
        ])->assertOk();

        $user = User::where('username', 'pass.user')->firstOrFail();
        $this->assertTrue(
            password_verify('كلمة۱۲۳عبور', $user->password),
            'رمز عبور باید دقیقاً همان چیزی باشد که کاربر تایپ کرده است.'
        );
    }

    public function test_search_finds_records_regardless_of_keyboard_used(): void
    {
        $this->makeAdmin();
        User::factory()->create(['name' => 'یحیی', 'family' => 'رشیدی', 'username' => 'y1', 'personnel_code' => '51228']);

        // جست‌وجو با ک/ی عربی و ارقام فارسی باید همان رکورد را پیدا کند
        foreach (['يحيي', 'یحیی', 'رشيدي'] as $term) {
            $this->actingAs(User::where('username', 'admin.test')->first())
                ->get(route('admin.users.index', ['q' => $term]))
                ->assertOk()
                ->assertSee('رشیدی');
        }

        $this->actingAs(User::where('username', 'admin.test')->first())
            ->get(route('admin.users.index', ['q' => '۵۱۲۲۸']))  // ارقام فارسی
            ->assertOk()
            ->assertSee('51228');
    }

    public function test_position_titles_are_stored_normalized(): void
    {
        $this->makeAdmin();

        $this->actingAs(User::where('username', 'admin.test')->first())
            ->post(route('admin.overtime.positions.store'), ['title' => 'كارشناس منابع آب', 'is_active' => 1])
            ->assertOk();

        $this->assertSame('کارشناس منابع آب', Position::first()->title);
    }

    /** کاربر ادمین با همه‌ی دسترسی‌ها. */
    private function makeAdmin(): User
    {
        // UserController وقتی نقشی برای کاربر جدید تعیین نشود به نقش پیش‌فرض «user»
        // برمی‌گردد؛ اگر وجود نداشته باشد، درج در role_user با خطا مواجه می‌شود.
        \App\Models\Role::firstOrCreate(['name' => 'user']);

        $role = \App\Models\Role::firstOrCreate(['name' => 'admin']);

        foreach (['admin/users', 'overtime/org-chart-manage'] as $key) {
            $permission = \App\Models\Permission::firstOrCreate(['key' => $key], ['title' => $key]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $admin = User::factory()->create(['username' => 'admin.test']);
        $admin->roles()->attach($role->id);
        User::forgetPermissionCache($admin->id);

        return $admin;
    }
}
