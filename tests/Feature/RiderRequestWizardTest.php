<?php

namespace Tests\Feature;

use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RiderRequestWizardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_rider_can_register_a_four_wheeled_vehicle(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);

        $response = $this->actingAs($rider)->post(route('rider.vehicles.store'), [
            'brand' => 'Toyota',
            'model' => 'Vios',
            'year' => 2023,
            'vehicle_type' => 'Car',
            'vehicle_category' => 'four_wheeled',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('vehicles', [
            'user_id' => $rider->id,
            'brand' => 'Toyota',
            'model' => 'Vios',
            'vehicle_category' => 'four_wheeled',
        ]);
    }

    public function test_rider_can_submit_multiple_services_with_location_and_notes(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        $vehicle = Vehicle::create([
            'user_id' => $rider->id,
            'brand' => 'Toyota',
            'model' => 'Vios',
            'year' => 2023,
            'vehicle_type' => 'Car',
            'vehicle_category' => 'four_wheeled',
        ]);

        $response = $this->actingAs($rider)->post(route('rider.requests.store'), [
            'vehicle_id' => $vehicle->id,
            'services' => ['Flat tire', 'Battery problem'],
            'latitude' => '13.4112',
            'longitude' => '121.1803',
            'landmark' => 'Calapan City Hall',
            'notes' => 'The vehicle is parked near the entrance.',
        ]);

        $serviceRequest = $rider->riderRequests()->latest()->firstOrFail();
        $response->assertRedirect(route('rider.requests.show', $serviceRequest));
        $this->assertSame(['Flat tire', 'Battery problem'], $serviceRequest->services);
        $this->assertSame('Flat tire', $serviceRequest->issue_category);
        $this->assertSame('The vehicle is parked near the entrance.', $serviceRequest->notes);
        $this->assertSame('13.4112000', $serviceRequest->latitude);
        $this->assertSame('121.1803000', $serviceRequest->longitude);
    }

    public function test_request_rejects_an_empty_service_selection(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        $vehicle = Vehicle::create([
            'user_id' => $rider->id,
            'brand' => 'Honda',
            'model' => 'Click',
            'year' => 2024,
            'vehicle_type' => 'Scooter',
            'vehicle_category' => 'two_wheeled',
        ]);

        $response = $this->actingAs($rider)
            ->from(route('rider.request-help'))
            ->post(route('rider.requests.store'), [
                'vehicle_id' => $vehicle->id,
                'latitude' => '13.4112',
                'longitude' => '121.1803',
            ]);

        $response->assertRedirect(route('rider.request-help'))
            ->assertSessionHasErrors(['services', 'issue_category']);
        $this->assertDatabaseMissing('service_requests', ['rider_id' => $rider->id]);
    }

    public function test_rider_request_help_renders_mobile_service_steps_and_breadcrumbs(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);

        $response = $this->actingAs($rider)->get(route('rider.request-help'));

        $response->assertOk()
            ->assertSee('Select Vehicle')
            ->assertSee('Select Services')
            ->assertSee('Pin Location')
            ->assertSee('Add Notes')
            ->assertSee('aria-label="Breadcrumb"', false)
            ->assertSee('service-options', false)
            ->assertDontSee('>Latitude<', false)
            ->assertDontSee('>Longitude<', false);
    }

    public function test_rider_dashboard_is_separate_from_vehicle_history_and_request_pages(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);

        $response = $this->actingAs($rider)->get(route('rider.dashboard'));

        $response->assertOk()
            ->assertSee('Mechanic discovery')
            ->assertSee('Quick access')
            ->assertDontSee('Request roadside help')
            ->assertDontSee('Add a vehicle')
            ->assertDontSee('All service requests');
    }

    public function test_rider_vehicles_page_shows_vehicle_management_separately(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        Vehicle::create([
            'user_id' => $rider->id,
            'brand' => 'Honda',
            'model' => 'Click',
            'year' => 2024,
            'vehicle_type' => 'Scooter',
            'vehicle_category' => 'two_wheeled',
        ]);

        $response = $this->actingAs($rider)->get(route('rider.vehicles.index'));

        $response->assertOk()
            ->assertSee('Registered vehicles')
            ->assertSee('Honda Click')
            ->assertSee('Add a vehicle')
            ->assertDontSee('Select Services');
    }

    public function test_rider_history_page_shows_requests_separately(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        $vehicle = Vehicle::create([
            'user_id' => $rider->id,
            'brand' => 'Toyota',
            'model' => 'Vios',
            'year' => 2023,
            'vehicle_type' => 'Car',
            'vehicle_category' => 'four_wheeled',
        ]);
        $serviceRequest = ServiceRequest::create([
            'reference' => 'FM-TEST-100',
            'rider_id' => $rider->id,
            'vehicle_id' => $vehicle->id,
            'issue_category' => 'Flat tire',
            'problem_description' => 'A tire is flat.',
            'latitude' => 13.4112,
            'longitude' => 121.1803,
            'dispatch_status' => 'pending',
            'service_status' => 'pending',
        ]);

        $response = $this->actingAs($rider)->get(route('rider.history'));

        $response->assertOk()
            ->assertSee($serviceRequest->reference)
            ->assertSee('Toyota Vios')
            ->assertSee('Flat tire')
            ->assertSee('Waiting')
            ->assertDontSee('Select Services');

        $this->assertSame('pending', $serviceRequest->fresh()->service_status);
    }
}
