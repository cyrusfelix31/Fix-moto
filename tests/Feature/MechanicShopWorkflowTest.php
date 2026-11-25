<?php

namespace Tests\Feature;

use App\Models\MechanicProfile;
use App\Models\MechanicShopMember;
use App\Models\ServiceRequest;
use App\Models\User;
use Database\Seeders\CalapanBarangaySeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MechanicShopWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_shop_registration_uses_a_map_pin_without_coordinate_fields(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk()
            ->assertSee('shop-location-map', false)
            ->assertDontSee('>Latitude<', false)
            ->assertDontSee('>Longitude<', false);
    }

    public function test_shop_registration_creates_a_profile_and_named_mechanic_roster(): void
    {
        $this->seed(CalapanBarangaySeeder::class);

        $response = $this->post(route('register'), [
            'name' => 'Shop Manager',
            'email' => 'manager@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'mechanic',
            'shop_name' => 'Calapan Vehicle Repair',
            'service_address' => 'Camilmil, Calapan City',
            'latitude' => '13.4112',
            'longitude' => '121.1803',
            'mechanics' => [
                ['name' => 'Mechanic One'],
                ['name' => 'Mechanic Two'],
            ],
        ]);

        $shop = User::where('email', 'manager@example.com')->firstOrFail();
        $response->assertRedirect(route('mechanic.dashboard'));
        $this->assertDatabaseHas('mechanic_profiles', [
            'user_id' => $shop->id,
            'shop_name' => 'Calapan Vehicle Repair',
            'latitude' => '13.4112000',
            'longitude' => '121.1803000',
            'barangay' => 'San Vicente East',
            'barangay_match_status' => 'matched',
        ]);
        $this->assertDatabaseHas('mechanic_shop_members', [
            'mechanic_profile_id' => $shop->mechanicProfile->id,
            'name' => 'Mechanic One',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('mechanic_shop_members', [
            'mechanic_profile_id' => $shop->mechanicProfile->id,
            'name' => 'Mechanic Two',
            'is_active' => true,
        ]);
    }

    public function test_existing_shop_can_update_its_dispatch_location(): void
    {
        $this->seed(CalapanBarangaySeeder::class);
        [$shop, $profile] = $this->createShop();

        $response = $this->actingAs($shop)
            ->from(route('mechanic.shop-profile'))
            ->post(route('mechanic.shop-profile.update'), [
                'shop_name' => 'Updated Repair Shop',
                'service_address' => 'Barangay Lalud, Calapan City',
                'latitude' => '13.4112',
                'longitude' => '121.1803',
            ]);

        $response->assertRedirect(route('mechanic.shop-profile'));
        $this->assertDatabaseHas('mechanic_profiles', [
            'id' => $profile->id,
            'shop_name' => 'Updated Repair Shop',
            'service_address' => 'Barangay Lalud, Calapan City',
            'latitude' => '13.4112000',
            'longitude' => '121.1803000',
            'barangay' => 'San Vicente East',
            'barangay_match_status' => 'matched',
        ]);
    }

    public function test_shop_profile_rejects_locations_outside_the_service_area(): void
    {
        $this->seed(CalapanBarangaySeeder::class);
        [$shop, $profile] = $this->createShop();

        $response = $this->actingAs($shop)
            ->from(route('mechanic.shop-profile'))
            ->post(route('mechanic.shop-profile.update'), [
                'shop_name' => 'Updated Repair Shop',
                'service_address' => 'Outside Calapan',
                'latitude' => '12.9000',
                'longitude' => '121.1900',
            ]);

        $response->assertRedirect(route('mechanic.shop-profile'))
            ->assertSessionHasErrors('latitude', 'Choose a shop location within Calapan City boundaries.');
        $this->assertDatabaseHas('mechanic_profiles', [
            'id' => $profile->id,
            'latitude' => '13.4112000',
        ]);
    }

    public function test_shop_registration_rejects_locations_outside_calapan_city(): void
    {
        $this->seed(CalapanBarangaySeeder::class);

        $response = $this->from(route('register'))->post(route('register'), [
            'name' => 'Shop Manager',
            'email' => 'outside-shop@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'mechanic',
            'shop_name' => 'Outside Repair Shop',
            'service_address' => 'Outside Calapan',
            'latitude' => '12.9000',
            'longitude' => '121.1900',
            'mechanics' => [
                ['name' => 'Mechanic One'],
            ],
        ]);

        $response->assertRedirect(route('register'))
            ->assertSessionHasErrors('latitude', 'Choose a shop location within Calapan City boundaries.');
        $this->assertDatabaseMissing('users', ['email' => 'outside-shop@example.com']);
    }

    public function test_shop_dashboard_renders_roster_and_shop_profile_controls(): void
    {
        [$shop] = $this->createShop(withMember: true);
        $serviceRequest = $this->createAssignedRequest($shop);

        $response = $this->actingAs($shop)->get(route('mechanic.dashboard'));

        $response->assertOk()
            ->assertSee('Mechanic shop dashboard')
            ->assertSee(route('mechanic.shop-requests'), false)
            ->assertSee(route('mechanic.team'), false)
            ->assertSee(route('mechanic.shop-profile'), false)
            ->assertDontSee($serviceRequest->reference);
    }

    public function test_shop_requests_team_and_profile_are_separate_pages(): void
    {
        [$shop, , $member] = $this->createShop(withMember: true);
        $serviceRequest = $this->createAssignedRequest($shop);

        $requestsPage = $this->actingAs($shop)->get(route('mechanic.shop-requests'));
        $requestsPage->assertOk()
            ->assertDontSee('Incoming offers')
            ->assertDontSee('No pending dispatch offers.')
            ->assertSee('Active requests')
            ->assertSee($serviceRequest->reference)
            ->assertSee(route('mechanic.requests.assign', $serviceRequest), false)
            ->assertDontSee('Shop roster');

        $teamPage = $this->actingAs($shop)->get(route('mechanic.team'));
        $teamPage->assertOk()
            ->assertSee('Shop roster')
            ->assertSee($member->name)
            ->assertDontSee($serviceRequest->reference)
            ->assertDontSee('Shop details and location');

        $profilePage = $this->actingAs($shop)->get(route('mechanic.shop-profile'));
        $profilePage->assertOk()
            ->assertSee('shop-profile-map', false)
            ->assertSee('Shop details and location')
            ->assertSee('Shop location successfully pinned.')
            ->assertDontSee($serviceRequest->reference)
            ->assertDontSee('Shop roster')
            ->assertDontSee('>Latitude<', false)
            ->assertDontSee('>Longitude<', false);
    }

    public function test_completed_requests_show_detailed_completion_information(): void
    {
        [$shop, , $member] = $this->createShop(withMember: true);
        $serviceRequest = $this->createAssignedRequest($shop, $member);
        $serviceRequest->update(['service_status' => 'completed']);
        $serviceRequest->statusHistory()->create([
            'status' => 'completed',
            'note' => null,
            'changed_by' => $shop->id,
        ]);

        $response = $this->actingAs($shop)->get(route('mechanic.shop-requests'));

        $response->assertOk()
            ->assertSee('Recently completed')
            ->assertSee($serviceRequest->reference)
            ->assertSee($serviceRequest->rider->name)
            ->assertSee($member->name)
            ->assertSee('Toyota')
            ->assertSee('Vios')
            ->assertSee('Flat tire')
            ->assertSee('A tire is flat.')
            ->assertSee('Completed ');
    }

    public function test_non_mechanics_cannot_access_shop_pages(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);

        $this->actingAs($rider)->get(route('mechanic.shop-requests'))->assertForbidden();
        $this->actingAs($rider)->get(route('mechanic.team'))->assertForbidden();
        $this->actingAs($rider)->get(route('mechanic.shop-profile'))->assertForbidden();
    }

    public function test_rider_request_is_offered_to_a_verified_online_shop_with_active_mechanics(): void
    {
        [$shop, $profile, $member] = $this->createShop(withMember: true);
        $rider = User::factory()->create(['role' => 'rider']);

        $serviceRequest = $this->createRiderRequest($rider);
        $attempt = $serviceRequest->dispatchAttempts()->firstOrFail();

        $this->assertSame('offered', $serviceRequest->fresh()->dispatch_status);
        $this->assertSame($shop->id, $attempt->mechanic_id);
        $this->assertSame($profile->id, $member->mechanicProfile->id);
        $this->assertDatabaseHas('dispatch_attempts', [
            'service_request_id' => $serviceRequest->id,
            'mechanic_id' => $shop->id,
            'status' => 'offered',
        ]);
    }

    public function test_shop_without_active_mechanics_is_not_offered_rider_requests(): void
    {
        $this->createShop(withMember: false);
        $rider = User::factory()->create(['role' => 'rider']);

        $serviceRequest = $this->createRiderRequest($rider);

        $this->assertSame('no_mechanics', $serviceRequest->fresh()->dispatch_status);
        $this->assertDatabaseMissing('dispatch_attempts', [
            'service_request_id' => $serviceRequest->id,
        ]);
    }

    public function test_shop_accepts_request_assigns_mechanic_and_advances_service_status(): void
    {
        [$shop, , $member] = $this->createShop(withMember: true);
        $rider = User::factory()->create(['role' => 'rider']);
        $serviceRequest = $this->createRiderRequest($rider);
        $attempt = $serviceRequest->dispatchAttempts()->firstOrFail();

        $this->actingAs($shop)
            ->post(route('mechanic.offers.accept', $attempt))
            ->assertRedirect();
        $this->assertDatabaseHas('service_requests', [
            'id' => $serviceRequest->id,
            'mechanic_id' => $shop->id,
            'dispatch_status' => 'assigned',
            'service_status' => 'accepted',
        ]);

        $this->actingAs($shop)
            ->post(route('mechanic.requests.status', $serviceRequest), ['status' => 'on_the_way'])
            ->assertUnprocessable();
        $this->assertDatabaseHas('service_requests', [
            'id' => $serviceRequest->id,
            'service_status' => 'accepted',
            'mechanic_shop_member_id' => null,
        ]);

        $this->actingAs($shop)
            ->post(route('mechanic.requests.assign', $serviceRequest), [
                'mechanic_shop_member_id' => $member->id,
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('service_requests', [
            'id' => $serviceRequest->id,
            'mechanic_shop_member_id' => $member->id,
        ]);

        $this->actingAs($shop)
            ->post(route('mechanic.requests.status', $serviceRequest), ['status' => 'on_the_way'])
            ->assertRedirect();
        $this->assertDatabaseHas('service_requests', [
            'id' => $serviceRequest->id,
            'service_status' => 'on_the_way',
        ]);
    }

    public function test_shop_cannot_assign_a_mechanic_from_another_shop(): void
    {
        [$shop] = $this->createShop(withMember: true);
        [, , $otherShopMember] = $this->createShop(withMember: true);
        $serviceRequest = $this->createAssignedRequest($shop);

        $response = $this->actingAs($shop)->post(route('mechanic.requests.assign', $serviceRequest), [
            'mechanic_shop_member_id' => $otherShopMember->id,
        ]);

        $response->assertNotFound();
        $this->assertDatabaseHas('service_requests', [
            'id' => $serviceRequest->id,
            'mechanic_shop_member_id' => null,
        ]);
    }

    public function test_mechanic_cannot_be_assigned_to_two_active_requests(): void
    {
        [$shop, , $member] = $this->createShop(withMember: true);
        $firstRequest = $this->createAssignedRequest($shop, $member);
        $secondRequest = $this->createAssignedRequest($shop);

        $response = $this->actingAs($shop)->post(route('mechanic.requests.assign', $secondRequest), [
            'mechanic_shop_member_id' => $member->id,
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseHas('service_requests', [
            'id' => $firstRequest->id,
            'mechanic_shop_member_id' => $member->id,
        ]);
        $this->assertDatabaseHas('service_requests', [
            'id' => $secondRequest->id,
            'mechanic_shop_member_id' => null,
        ]);
    }

    /**
     * @return array{User, MechanicProfile, ?MechanicShopMember}
     */
    private function createShop(bool $withMember = false): array
    {
        MechanicProfile::query()->where('is_online', true)->update(['is_online' => false]);
        $shop = User::factory()->create(['role' => 'mechanic']);
        $profile = MechanicProfile::factory()->for($shop, 'mechanic')->create([
            'shop_name' => 'Calapan Vehicle Shop',
            'service_address' => 'Calapan City',
            'latitude' => 13.4112,
            'longitude' => 121.1803,
            'is_verified' => true,
            'is_online' => true,
        ]);
        $member = $withMember ? $profile->members()->create(['name' => 'Available Mechanic']) : null;

        return [$shop, $profile, $member];
    }

    private function createRiderRequest(User $rider): ServiceRequest
    {
        $vehicle = $rider->vehicles()->create([
            'brand' => 'Toyota',
            'model' => 'Vios',
            'year' => 2023,
            'vehicle_type' => 'Car',
            'vehicle_category' => 'four_wheeled',
        ]);

        $this->actingAs($rider)->post(route('rider.requests.store'), [
            'vehicle_id' => $vehicle->id,
            'services' => ['Flat tire'],
            'latitude' => '13.4112',
            'longitude' => '121.1803',
            'notes' => 'Near the entrance.',
        ])->assertRedirect();

        return $rider->riderRequests()->latest()->firstOrFail();
    }

    private function createAssignedRequest(User $shop, ?MechanicShopMember $member = null): ServiceRequest
    {
        $rider = User::factory()->create(['role' => 'rider']);
        $vehicle = $rider->vehicles()->create([
            'brand' => 'Toyota',
            'model' => 'Vios',
            'year' => 2023,
            'vehicle_type' => 'Car',
            'vehicle_category' => 'four_wheeled',
        ]);

        return ServiceRequest::create([
            'reference' => 'FM-'.strtoupper(bin2hex(random_bytes(5))),
            'rider_id' => $rider->id,
            'vehicle_id' => $vehicle->id,
            'mechanic_id' => $shop->id,
            'mechanic_shop_member_id' => $member?->id,
            'issue_category' => 'Flat tire',
            'services' => ['Flat tire'],
            'problem_description' => 'A tire is flat.',
            'latitude' => 13.4112,
            'longitude' => 121.1803,
            'dispatch_status' => 'assigned',
            'service_status' => 'accepted',
        ]);
    }
}
