<?php

namespace Tests\Feature;

use App\Models\MechanicProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use DatabaseTransactions;

    public function test_home_redirects_to_login(): void
    {
        $response = $this->get('/');
        $response->assertRedirect('/login');
    }

    public function test_rider_can_register_and_reach_dashboard(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test Rider', 'email' => 'rider@example.com', 'password' => 'password123', 'password_confirmation' => 'password123', 'role' => 'rider',
        ]);
        $response->assertRedirect('/rider/dashboard');
        $this->assertDatabaseHas('users', ['email' => 'rider@example.com', 'role' => 'rider']);
    }

    public function test_role_restrictions_protect_dashboards(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        $this->actingAs($rider)->get('/admin/dashboard')->assertForbidden();
        $this->actingAs($rider)->get('/rider/dashboard')->assertOk();
    }

    public function test_non_riders_cannot_access_rider_vehicle_pages(): void
    {
        $mechanic = User::factory()->create(['role' => 'mechanic']);

        $this->actingAs($mechanic)->get(route('rider.vehicles.index'))->assertForbidden();
    }

    public function test_rider_map_loads_leaflet_and_pins_all_verified_mechanic_shops(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        $availableMechanic = User::factory()->create(['role' => 'mechanic']);
        $availableShop = MechanicProfile::create([
            'user_id' => $availableMechanic->id,
            'shop_name' => 'Marco Vehicle Repair',
            'service_address' => 'Calapan City, Oriental Mindoro',
            'latitude' => 13.4113,
            'longitude' => 121.1810,
            'is_verified' => true,
            'is_online' => true,
        ]);
        $offlineMechanic = User::factory()->create(['role' => 'mechanic']);
        $offlineShop = MechanicProfile::create([
            'user_id' => $offlineMechanic->id,
            'shop_name' => 'Offline Vehicle Repair',
            'service_address' => 'Calapan City, Oriental Mindoro',
            'latitude' => 13.4200,
            'longitude' => 121.1900,
            'is_verified' => true,
            'is_online' => false,
        ]);
        $unverifiedMechanic = User::factory()->create(['role' => 'mechanic']);
        MechanicProfile::create([
            'user_id' => $unverifiedMechanic->id,
            'shop_name' => 'Pending Vehicle Shop',
            'service_address' => 'Calapan City, Oriental Mindoro',
            'latitude' => 13.4300,
            'longitude' => 121.2000,
            'is_verified' => false,
            'is_online' => false,
        ]);

        $response = $this->actingAs($rider)->get('/rider/dashboard');

        $response->assertOk()
            ->assertSeeInOrder([
                'leaflet.js',
                "const riderMap = L.map('rider-map')",
            ])
            ->assertSee('Marco Vehicle Repair')
            ->assertSee('L.marker(['.$availableShop->latitude.', '.$availableShop->longitude.'])', false)
            ->assertSee('Offline Vehicle Repair')
            ->assertSee('Offline')
            ->assertSee('L.marker(['.$offlineShop->latitude.', '.$offlineShop->longitude.'])', false)
            ->assertDontSee('Pending Vehicle Shop');
    }

    public function test_rider_can_add_vehicle(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        $response = $this->actingAs($rider)->post('/rider/vehicles', [
            'brand' => 'Honda', 'model' => 'Click', 'year' => 2024, 'plate_number' => 'ABC-123', 'vehicle_type' => 'Scooter', 'vehicle_category' => 'two_wheeled',
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('vehicles', ['user_id' => $rider->id, 'brand' => 'Honda', 'model' => 'Click']);
    }

    public function test_example_accounts_can_login(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $accounts = [
            'rider' => '/rider/dashboard',
            'mechanic' => '/mechanic/dashboard',
            'admin' => '/admin/dashboard',
        ];

        foreach ($accounts as $email => $destination) {
            auth()->logout();
            $response = $this->post('/login', ['email' => $email, 'password' => '12345']);
            $response->assertRedirect($destination);
        }
    }

    public function test_admin_can_update_system_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($admin)->post('/admin/system-status', [
            'status' => 'downtime',
            'reason' => 'Scheduled maintenance',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('system_status_incidents', [
            'status' => 'downtime',
            'reason' => 'Scheduled maintenance',
            'recorded_by' => $admin->id,
        ]);
    }

    public function test_admin_directory_shows_riders_and_mechanics_but_not_admin_accounts(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Directory Admin', 'email' => 'directory-admin@example.com']);
        User::factory()->create(['role' => 'rider', 'name' => 'Directory Rider']);
        User::factory()->create(['role' => 'mechanic', 'name' => 'Directory Mechanic']);

        $response = $this->actingAs($admin)->get('/admin/users-mechanics');

        $response->assertOk()
            ->assertSee('Directory Rider')
            ->assertSee('Directory Mechanic')
            ->assertDontSee('directory-admin@example.com');
    }

    public function test_admin_directory_search_does_not_include_admin_accounts(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Hidden Admin', 'email' => 'secret-admin@example.com']);

        $response = $this->actingAs($admin)->get('/admin/users-mechanics?search=secret-admin@example.com');

        $response->assertOk()
            ->assertSee('No accounts found.');
    }

    public function test_admin_directory_displays_active_and_inactive_account_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $activeUser = User::factory()->create(['role' => 'rider', 'name' => 'Active Rider', 'is_suspended' => false]);
        $inactiveUser = User::factory()->create(['role' => 'rider', 'name' => 'Inactive Rider', 'is_suspended' => true]);

        $response = $this->actingAs($admin)->get('/admin/users-mechanics');

        $response->assertOk()
            ->assertSee('Active')
            ->assertSee('Inactive')
            ->assertSee('Activate')
            ->assertSee('Suspend');
    }
}
