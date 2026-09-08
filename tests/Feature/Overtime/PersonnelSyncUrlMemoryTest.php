<?php

namespace Tests\Feature\Overtime;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * صفحه‌ی همگام‌سازی باید آخرین آدرس واردشده را به یاد بسپارد.
 *
 * پیش‌تر فیلد آدرس همیشه مقدار فایل پیکربندی را نشان می‌داد، پس کاربری که آدرس
 * دیگری وارد می‌کرد مجبور بود هر بار دوباره تایپش کند.
 */
class PersonnelSyncUrlMemoryTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'overtime.personnel_sync_url';

    private function hrUser(): User
    {
        $role = Role::firstOrCreate(['name' => 'hr']);
        $role->permissions()->syncWithoutDetaching([
            Permission::firstOrCreate(['key' => 'overtime/hr-manage'], ['title' => 'hr'])->id,
            Permission::firstOrCreate(['key' => 'menu/personnel-sync'], ['title' => 'sync'])->id,
        ]);

        $user = User::factory()->create(['username' => 'hr-user']);
        $user->roles()->attach($role->id);
        User::forgetPermissionCache($user->id);

        return $user;
    }

    /** سرویس دو درخواست می‌زند: اول توکن، بعد داده. */
    private function fakeService(array $records = []): void
    {
        Http::fake([
            '*openid-connect/token' => Http::response(['access_token' => 'fake'], 200),
            '*' => Http::response($records, 200),
        ]);

        config(['services.personnel.token_url' => 'https://example.test/openid-connect/token']);
    }

    public function test_the_entered_url_is_remembered_after_a_successful_sync(): void
    {
        $this->fakeService();

        $this->actingAs($this->hrUser())
            ->postJson(route('admin.overtime.sync.store'), ['url' => 'http://new.test/api/personnel.json'])
            ->assertOk();

        $this->assertSame(
            'http://new.test/api/personnel.json',
            Setting::where('key', self::KEY)->value('value')
        );
    }

    public function test_the_saved_url_is_shown_on_the_page(): void
    {
        Setting::create(['key' => self::KEY, 'value' => 'http://saved.test/api.json']);
        config(['services.personnel.url' => 'http://config.test/api.json']);

        $this->actingAs($this->hrUser())
            ->get(route('admin.overtime.sync.index'))
            ->assertOk()
            ->assertSee('http://saved.test/api.json')
            ->assertDontSee('http://config.test/api.json');
    }

    public function test_the_config_value_is_used_when_nothing_was_saved_yet(): void
    {
        config(['services.personnel.url' => 'http://config.test/api.json']);

        $this->actingAs($this->hrUser())
            ->get(route('admin.overtime.sync.index'))
            ->assertOk()
            ->assertSee('http://config.test/api.json');
    }

    public function test_a_failed_sync_does_not_overwrite_the_saved_url(): void
    {
        // آدرسی که کار نکرد نباید به‌عنوان پیش‌فرض دفعه‌ی بعد بماند.
        Setting::create(['key' => self::KEY, 'value' => 'http://good.test/api.json']);

        Http::fake([
            '*openid-connect/token' => Http::response(['access_token' => 'fake'], 200),
            '*' => Http::response('', 500),
        ]);
        config(['services.personnel.token_url' => 'https://example.test/openid-connect/token']);

        $this->actingAs($this->hrUser())
            ->postJson(route('admin.overtime.sync.store'), ['url' => 'http://broken.test/api.json'])
            ->assertStatus(422);

        $this->assertSame('http://good.test/api.json', Setting::where('key', self::KEY)->value('value'));
    }

    public function test_submitting_an_empty_url_clears_the_saved_one(): void
    {
        // تنها راه برگشتن به مقدار فایل پیکربندی.
        Setting::create(['key' => self::KEY, 'value' => 'http://old.test/api.json']);
        $this->fakeService();

        $this->actingAs($this->hrUser())
            ->postJson(route('admin.overtime.sync.store'), ['url' => ''])
            ->assertOk();

        $this->assertDatabaseMissing('settings', ['key' => self::KEY]);
    }

    public function test_an_invalid_url_is_rejected_and_nothing_is_saved(): void
    {
        $this->actingAs($this->hrUser())
            ->postJson(route('admin.overtime.sync.store'), ['url' => 'not-a-url'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['url']);

        $this->assertDatabaseMissing('settings', ['key' => self::KEY]);
    }
}
