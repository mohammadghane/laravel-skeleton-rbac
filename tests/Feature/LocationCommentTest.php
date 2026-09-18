<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\LocationComment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * منوی «ثبت نظر» - مجاز بودن بر اساس فاصله تا موقعیت‌های تعریف‌شده.
 *
 * مختصات مرجع: میدان آزادی تهران. برای ساختن نقطه‌ای در فاصله‌ی مشخص، از این
 * تقریب استفاده شده که ۱ درجه عرض جغرافیایی ≈ ۱۱۱٬۱۹۵ متر است (مستقل از طول
 * جغرافیایی، چون فقط عرض را تغییر می‌دهیم).
 */
class LocationCommentTest extends TestCase
{
    use RefreshDatabase;

    private const BASE_LAT = 35.6997161;
    private const BASE_LNG = 51.3376820;
    private const METERS_PER_DEGREE_LAT = 111195.0;

    /** نقطه‌ای دقیقاً $meters متر شمالِ مبنا. */
    private function pointNorthOfBase(float $meters): array
    {
        return [
            self::BASE_LAT + ($meters / self::METERS_PER_DEGREE_LAT),
            self::BASE_LNG,
        ];
    }

    private function user(): User
    {
        $role = Role::firstOrCreate(['name' => 'commenter']);
        $role->permissions()->syncWithoutDetaching([
            Permission::firstOrCreate(['key' => 'menu/location-comments'], ['title' => 'c'])->id,
        ]);

        $user = User::factory()->create(['username' => 'commenter']);
        $user->roles()->attach($role->id);
        User::forgetPermissionCache($user->id);

        return $user;
    }

    private function location(int $radius = 100): Location
    {
        return Location::create([
            'title' => 'ساختمان مرکزی',
            'latitude' => self::BASE_LAT,
            'longitude' => self::BASE_LNG,
            'allowed_radius' => $radius,
            'is_active' => true,
        ]);
    }

    public function test_a_point_inside_the_radius_is_allowed(): void
    {
        $this->location(100);
        [$lat, $lng] = $this->pointNorthOfBase(50);

        $this->actingAs($this->user())
            ->postJson(route('admin.location-comments.check'), ['latitude' => $lat, 'longitude' => $lng])
            ->assertOk()
            ->assertJson(['allowed' => true, 'message' => 'شما مجاز هستید.']);
    }

    public function test_a_point_outside_the_radius_is_not_allowed(): void
    {
        $this->location(100);
        [$lat, $lng] = $this->pointNorthOfBase(250);

        $this->actingAs($this->user())
            ->postJson(route('admin.location-comments.check'), ['latitude' => $lat, 'longitude' => $lng])
            ->assertOk()
            ->assertJson(['allowed' => false, 'message' => 'شما مجاز نیستید.']);
    }

    public function test_the_radius_boundary_is_respected(): void
    {
        // همان نقطه، با دو شعاع متفاوت: تنها متغیر، عدد n است.
        [$lat, $lng] = $this->pointNorthOfBase(150);
        $location = $this->location(100);

        $user = $this->user();

        $this->actingAs($user)
            ->postJson(route('admin.location-comments.check'), ['latitude' => $lat, 'longitude' => $lng])
            ->assertJson(['allowed' => false]);

        $location->update(['allowed_radius' => 200]);

        $this->actingAs($user)
            ->postJson(route('admin.location-comments.check'), ['latitude' => $lat, 'longitude' => $lng])
            ->assertJson(['allowed' => true]);
    }

    public function test_an_inactive_location_does_not_grant_access(): void
    {
        $this->location(100)->update(['is_active' => false]);
        [$lat, $lng] = $this->pointNorthOfBase(10);

        $this->actingAs($this->user())
            ->postJson(route('admin.location-comments.check'), ['latitude' => $lat, 'longitude' => $lng])
            ->assertJson(['allowed' => false]);
    }

    public function test_the_nearest_covering_location_is_reported(): void
    {
        // دو محدوده‌ی هم‌پوشان: نتیجه باید نزدیک‌ترین باشد، نه اولین.
        $this->location(1000);
        [$nearLat, $nearLng] = $this->pointNorthOfBase(100);
        Location::create([
            'title' => 'ساختمان نزدیک‌تر',
            'latitude' => $nearLat, 'longitude' => $nearLng,
            'allowed_radius' => 1000, 'is_active' => true,
        ]);

        [$lat, $lng] = $this->pointNorthOfBase(90);

        $this->actingAs($this->user())
            ->postJson(route('admin.location-comments.check'), ['latitude' => $lat, 'longitude' => $lng])
            ->assertJson(['allowed' => true, 'location' => 'ساختمان نزدیک‌تر']);
    }

    public function test_a_comment_inside_the_radius_is_saved_with_its_snapshot(): void
    {
        $location = $this->location(100);
        [$lat, $lng] = $this->pointNorthOfBase(40);

        $this->actingAs($this->user())
            ->postJson(route('admin.location-comments.store'), [
                'latitude' => $lat, 'longitude' => $lng, 'body' => 'وضعیت مناسب است.',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $saved = LocationComment::first();

        $this->assertTrue($saved->was_allowed);
        $this->assertSame($location->id, $saved->location_id);
        // فاصله‌ی ثبت‌شده باید به ۴۰ متر نزدیک باشد.
        $this->assertEqualsWithDelta(40, $saved->distance_meters, 2);
    }

    public function test_a_comment_outside_the_radius_is_rejected_by_the_server(): void
    {
        // مهم‌ترین تضمین: غیرفعال‌بودن دکمه در مرورگر محافظت نیست؛ درخواست مستقیم
        // هم باید رد شود.
        $this->location(100);
        [$lat, $lng] = $this->pointNorthOfBase(500);

        $this->actingAs($this->user())
            ->postJson(route('admin.location-comments.store'), [
                'latitude' => $lat, 'longitude' => $lng, 'body' => 'تلاش برای دور زدن',
            ])
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertSame(0, LocationComment::count());
    }

    public function test_a_comment_without_any_defined_location_is_rejected(): void
    {
        $this->actingAs($this->user())
            ->postJson(route('admin.location-comments.store'), [
                'latitude' => self::BASE_LAT, 'longitude' => self::BASE_LNG, 'body' => 'متن',
            ])
            ->assertStatus(422);
    }

    public function test_a_user_without_the_permission_cannot_reach_the_menu(): void
    {
        $stranger = User::factory()->create(['username' => 'nobody']);
        User::forgetPermissionCache($stranger->id);

        $this->actingAs($stranger)
            ->get(route('admin.location-comments.index'))
            ->assertForbidden();
    }

    public function test_the_distance_calculation_matches_reality(): void
    {
        // Haversine در برابر فاصله‌های معلوم؛ خطای بیش از ۱٪ یعنی فرمول اشتباه است.
        $location = $this->location();

        $this->assertEqualsWithDelta(0, $location->distanceTo(self::BASE_LAT, self::BASE_LNG), 0.5);

        [$lat, $lng] = $this->pointNorthOfBase(1000);
        $this->assertEqualsWithDelta(1000, $location->distanceTo($lat, $lng), 10);
    }
}
