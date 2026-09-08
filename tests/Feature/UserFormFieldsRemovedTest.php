<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * فیلدهای جنسیت، ایمیل و کد ملی از فرم پرسنل حذف شده‌اند (درخواست کارفرما).
 *
 * خطری که این تست‌ها می‌بندند: حذف فیلد از فرم بدون اصلاح قوانین اعتبارسنجی و
 * ستون دیتابیس، ثبت کاربر را کاملاً غیرممکن می‌کند - دقیقاً همان اتفاقی که پیش‌تر
 * برای فیلدهای آدرس افتاده بود (قانون required برای فیلدی که در فرم نبود).
 */
class UserFormFieldsRemovedTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $role = Role::firstOrCreate(['name' => 'admin']);
        $role->permissions()->syncWithoutDetaching([
            Permission::firstOrCreate(['key' => 'admin/users'], ['title' => 'admin/users'])->id,
            Permission::firstOrCreate(['key' => 'menu/personnel'], ['title' => 'menu/personnel'])->id,
        ]);
        Role::firstOrCreate(['name' => 'user']);

        $user = User::factory()->create(['username' => 'admin']);
        $user->roles()->attach($role->id);
        User::forgetPermissionCache($user->id);

        return $user;
    }

    public function test_a_user_can_be_created_without_gender_email_or_national_code(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('admin.users.store'), [
                'name' => 'علی',
                'family' => 'رضایی',
                'username' => 'ali.rezaei',
            ])
            ->assertOk();

        $created = User::where('username', 'ali.rezaei')->first();

        $this->assertNotNull($created, 'کاربر باید ساخته شود.');
        $this->assertNull($created->gender);
        $this->assertNull($created->email);
        $this->assertNull($created->national_code);
    }

    public function test_editing_a_user_does_not_wipe_their_existing_email(): void
    {
        // فرم دیگر ایمیل نمی‌فرستد؛ نباید این را «کاربر ایمیل را پاک کرده» تفسیر کرد.
        $admin = $this->admin();
        $target = User::factory()->create([
            'username' => 'has-email',
            'email' => 'old@example.com',
            'national_code' => '1234567890',
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.users.store'), [
                'id' => $target->id,
                'name' => 'نام تازه',
                'username' => 'has-email',
            ])
            ->assertOk();

        $target->refresh();

        $this->assertSame('نام تازه', $target->name);
        $this->assertSame('old@example.com', $target->email, 'ایمیل قبلی نباید پاک شود.');
        $this->assertSame('1234567890', $target->national_code, 'کد ملی قبلی نباید پاک شود.');
    }

    public function test_a_new_user_without_a_password_still_gets_a_valid_record(): void
    {
        // ستون password در جدول NOT NULL است و فرم رمز را اختیاری می‌داند؛ بدون
        // رمز تصادفیِ جایگزین، ثبت با خطای SQL شکست می‌خورد.
        $this->actingAs($this->admin())
            ->postJson(route('admin.users.store'), [
                'name' => 'بدون رمز',
                'username' => 'no-password',
            ])
            ->assertOk();

        $created = User::where('username', 'no-password')->first();

        $this->assertNotNull($created->password, 'رمز نباید خالی بماند.');
        $this->assertFalse(
            \Illuminate\Support\Facades\Hash::check('', $created->password),
            'رمز نباید رشته‌ی خالیِ قابل‌حدس باشد.'
        );
    }

    public function test_the_form_no_longer_renders_the_removed_fields(): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('admin.users.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('name="gender"', $html);
        $this->assertStringNotContainsString('name="email"', $html);
        $this->assertStringNotContainsString('name="national_code"', $html);

        // فیلدهای باقی‌مانده نباید قربانی شوند.
        $this->assertStringContainsString('name="username"', $html);
        $this->assertStringContainsString('name="cell"', $html);
    }

    public function test_values_sent_by_other_sources_are_still_validated(): void
    {
        // قوانین حذف نشده‌اند: اگر سرویس یا ایمپورت مقدار بفرستد باید معتبر باشد.
        $this->actingAs($this->admin())
            ->postJson(route('admin.users.store'), [
                'name' => 'تست',
                'username' => 'bad-values',
                'email' => 'not-an-email',
                'national_code' => '123',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'national_code']);
    }
}
