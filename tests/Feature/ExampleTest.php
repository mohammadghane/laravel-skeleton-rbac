<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ریشه‌ی سایت صفحه‌ی محتوایی ندارد و صرفاً هدایت‌کننده است:
 *   کاربر واردنشده => صفحه‌ی ورود
 *   کاربر واردشده  => صفحه‌ی فرود پنل
 */
class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_authenticated_users_are_redirected_to_the_panel(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertRedirect(route('admin.dashboard'));
    }
}
