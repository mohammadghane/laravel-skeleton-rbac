<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * تست دستور `user:password` - تغییر رمز عبور از خط فرمان.
 *
 * این دستور تنها راه بازیابی دسترسی وقتی است که کسی نتواند وارد پنل شود، پس باید
 * هم با نام کاربری کار کند، هم ایمیل و هم کد پرسنلی.
 */
class ResetUserPasswordCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_changes_the_password_by_username(): void
    {
        $user = User::factory()->create(['username' => 'admin', 'password' => Hash::make('old-password')]);

        $this->artisan('user:password', ['user' => 'admin', '--password' => 'new-password'])
            ->assertSuccessful();

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_it_finds_the_user_by_email(): void
    {
        $user = User::factory()->create(['email' => 'someone@example.test']);

        $this->artisan('user:password', ['user' => 'someone@example.test', '--password' => 'new-password'])
            ->assertSuccessful();

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_it_finds_the_user_by_personnel_code(): void
    {
        $user = User::factory()->create(['personnel_code' => '52155']);

        $this->artisan('user:password', ['user' => '52155', '--password' => 'new-password'])
            ->assertSuccessful();

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_it_fails_for_an_unknown_user(): void
    {
        $this->artisan('user:password', ['user' => 'ناموجود', '--password' => 'new-password'])
            ->assertFailed();
    }

    public function test_it_rejects_a_password_shorter_than_eight_characters(): void
    {
        // هم‌تراز با قانون min:8 در UserRequest - وگرنه دستور می‌توانست رمزی بسازد که
        // بعداً از طریق فرم پنل قابل تغییر نباشد.
        $user = User::factory()->create(['username' => 'short.pass', 'password' => Hash::make('original-password')]);

        $this->artisan('user:password', ['user' => 'short.pass', '--password' => '123'])
            ->assertFailed();

        $this->assertTrue(
            Hash::check('original-password', $user->fresh()->password),
            'رمز نامعتبر نباید ذخیره شود.'
        );
    }

    public function test_random_option_generates_a_valid_password(): void
    {
        $user = User::factory()->create(['username' => 'rand.user', 'password' => Hash::make('original-password')]);

        $this->artisan('user:password', ['user' => 'rand.user', '--random' => true])
            ->assertSuccessful();

        $this->assertFalse(Hash::check('original-password', $user->fresh()->password));
    }

    public function test_list_option_runs_without_a_user_argument(): void
    {
        User::factory()->create();

        $this->artisan('user:password', ['--list' => true])->assertSuccessful();
    }
}
