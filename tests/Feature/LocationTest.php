<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * منوی «موقعیت جغرافیایی» - ثبت نقطه روی نقشه‌ی OpenStreetMap.
 */
class LocationTest extends TestCase
{
    use RefreshDatabase;

    private function allowedUser(): User
    {
        $role = Role::firstOrCreate(['name' => 'gis']);
        $role->permissions()->syncWithoutDetaching([
            Permission::firstOrCreate(['key' => 'menu/locations'], ['title' => 'locations'])->id,
        ]);

        $user = User::factory()->create(['username' => 'gis-user']);
        $user->roles()->attach($role->id);
        User::forgetPermissionCache($user->id);

        return $user;
    }

    public function test_a_point_clicked_on_the_map_is_saved(): void
    {
        $user = $this->allowedUser();

        $this->actingAs($user)
            ->postJson(route('admin.locations.store'), [
                'title' => 'ساختمان مرکزی',
                'latitude' => 35.6997161,
                'longitude' => 51.3376820,
                'allowed_radius' => 150,
                'description' => 'دفتر مرکزی سازمان',
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $saved = Location::first();

        $this->assertSame('ساختمان مرکزی', $saved->title);
        // دقت اعشار نباید در رفت‌وبرگشت گم شود.
        $this->assertSame('35.6997161', (string) $saved->latitude);
        $this->assertSame('51.3376820', (string) $saved->longitude);
        $this->assertSame($user->id, $saved->created_by);
        $this->assertSame(150, $saved->allowed_radius);
    }

    public function test_saving_without_a_point_is_rejected(): void
    {
        $this->actingAs($this->allowedUser())
            ->postJson(route('admin.locations.store'), ['title' => 'بدون نقطه'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_coordinates_outside_the_valid_range_are_rejected(): void
    {
        // یک درخواست دستکاری‌شده نباید نقطه‌ای بیرون از کره‌ی زمین ثبت کند.
        $this->actingAs($this->allowedUser())
            ->postJson(route('admin.locations.store'), [
                'title' => 'نامعتبر',
                'latitude' => 120,
                'longitude' => 500,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_the_allowed_radius_is_required_and_must_be_positive(): void
    {
        // شعاع صفر یعنی هیچ نقطه‌ای هرگز مجاز نیست - که موقعیت را بی‌استفاده
        // می‌کند و احتمالاً اشتباه کاربر است، نه خواسته‌اش.
        $user = $this->allowedUser();

        $this->actingAs($user)
            ->postJson(route('admin.locations.store'), [
                'title' => 'بدون شعاع', 'latitude' => 35.7, 'longitude' => 51.4,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['allowed_radius']);

        $this->actingAs($user)
            ->postJson(route('admin.locations.store'), [
                'title' => 'شعاع صفر', 'latitude' => 35.7, 'longitude' => 51.4, 'allowed_radius' => 0,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['allowed_radius']);
    }

    public function test_a_location_can_be_moved_by_editing(): void
    {
        $user = $this->allowedUser();
        $location = Location::create([
            'title' => 'انبار', 'latitude' => 35.1, 'longitude' => 51.1, 'is_active' => true,
        ]);

        $this->actingAs($user)
            ->putJson(route('admin.locations.update', $location), [
                'title' => 'انبار مرکزی',
                'latitude' => 36.2,
                'longitude' => 52.2,
                'allowed_radius' => 250,
                'is_active' => false,
            ])
            ->assertOk();

        $location->refresh();

        $this->assertSame('انبار مرکزی', $location->title);
        $this->assertSame('36.2000000', (string) $location->latitude);
        $this->assertFalse($location->is_active, 'برداشتن تیک «فعال» باید ذخیره شود.');
    }

    public function test_the_listing_and_menu_are_visible_to_a_permitted_user(): void
    {
        Location::create(['title' => 'نقطه‌ی آزمون', 'latitude' => 35.7, 'longitude' => 51.4]);

        $this->actingAs($this->allowedUser())
            ->get(route('admin.locations.index'))
            ->assertOk()
            ->assertSee('نقطه‌ی آزمون')
            ->assertSee('موقعیت جغرافیایی');
    }

    public function test_a_user_without_the_permission_cannot_reach_the_menu(): void
    {
        $stranger = User::factory()->create(['username' => 'nobody']);
        User::forgetPermissionCache($stranger->id);

        $this->actingAs($stranger)
            ->get(route('admin.locations.index'))
            ->assertForbidden();
    }

    public function test_locations_can_be_deleted_in_bulk(): void
    {
        $user = $this->allowedUser();
        $a = Location::create(['title' => 'الف', 'latitude' => 35.1, 'longitude' => 51.1]);
        $b = Location::create(['title' => 'ب', 'latitude' => 35.2, 'longitude' => 51.2]);

        $this->actingAs($user)
            ->postJson(route('admin.locations.bulk-destroy'), ['ids' => [$a->id, $b->id]])
            ->assertOk();

        $this->assertSame(0, Location::count());
    }

    public function test_searching_filters_by_title_and_description(): void
    {
        Location::create(['title' => 'دفتر شمال', 'latitude' => 37.0, 'longitude' => 50.0]);
        Location::create(['title' => 'دفتر جنوب', 'latitude' => 29.0, 'longitude' => 52.0, 'description' => 'بندرعباس']);

        $response = $this->actingAs($this->allowedUser())
            ->get(route('admin.locations.index', ['q' => 'بندرعباس']))
            ->assertOk();

        $this->assertCount(1, $response->viewData('locations'));
        $this->assertSame('دفتر جنوب', $response->viewData('locations')->first()->title);
    }
}
