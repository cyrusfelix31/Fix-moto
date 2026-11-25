<?php

namespace Tests\Feature;

use App\Models\MechanicProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminMechanicVerificationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_verification_page_shows_pending_applications_and_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $mechanic = $this->createMechanic('Pending Mechanic');

        $response = $this->actingAs($admin)->get('/admin/verification');

        $response->assertOk()
            ->assertSee('Pending Mechanic')
            ->assertSee('Verification history')
            ->assertSee('Verification Checklist')
            ->assertSee('Valid Business Permit')
            ->assertSee("Owner's Valid ID", false)
            ->assertSee('Physical Shop Location')
            ->assertSee('Contact Number')
            ->assertSee('Shop Operations')
            ->assertSee('Mechanic Credentials')
            ->assertSee('Verified');
        $this->assertDatabaseMissing('mechanic_verifications', ['mechanic_id' => $mechanic->id]);
    }

    public function test_only_mechanics_are_listed_and_eligible_for_verification(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $mechanic = $this->createMechanic('Eligible Mechanic');
        $rider = User::factory()->create(['role' => 'rider']);
        $anotherAdmin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/admin/verification');

        $response->assertOk()
            ->assertSee(route('admin.mechanics.verify', $mechanic))
            ->assertDontSee(route('admin.mechanics.verify', $rider))
            ->assertDontSee(route('admin.mechanics.verify', $anotherAdmin));

        $this->actingAs($admin)
            ->post(route('admin.mechanics.verify', $rider), ['decision' => 'approve'])
            ->assertNotFound();
    }

    public function test_admin_approval_verifies_mechanic_and_records_review(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $mechanic = $this->createMechanic('Approved Mechanic');

        $response = $this->actingAs($admin)->post(route('admin.mechanics.verify', $mechanic), [
            'decision' => 'approve',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('mechanic_profiles', [
            'user_id' => $mechanic->id,
            'is_verified' => true,
            'is_online' => false,
        ]);
        $this->assertDatabaseHas('mechanic_verifications', [
            'mechanic_id' => $mechanic->id,
            'reviewed_by' => $admin->id,
            'status' => 'approved',
        ]);
    }

    public function test_rejected_application_requires_reason_and_moves_to_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $mechanic = $this->createMechanic('Rejected Mechanic');

        $this->actingAs($admin)
            ->from('/admin/verification')
            ->post(route('admin.mechanics.verify', $mechanic), ['decision' => 'reject'])
            ->assertSessionHasErrors('reason');

        $this->assertDatabaseMissing('mechanic_verifications', ['mechanic_id' => $mechanic->id]);

        $this->actingAs($admin)->post(route('admin.mechanics.verify', $mechanic), [
            'decision' => 'reject',
            'reason' => 'Documents could not be verified.',
        ])->assertRedirect();

        $this->assertDatabaseHas('mechanic_verifications', [
            'mechanic_id' => $mechanic->id,
            'reviewed_by' => $admin->id,
            'status' => 'rejected',
            'rejection_reason' => 'Documents could not be verified.',
        ]);

        $response = $this->actingAs($admin)->get('/admin/verification');
        $response->assertOk()
            ->assertDontSee(route('admin.mechanics.verify', $mechanic))
            ->assertSee('Documents could not be verified.');
    }

    private function createMechanic(string $name): User
    {
        $mechanic = User::factory()->create(['role' => 'mechanic', 'name' => $name]);

        MechanicProfile::create([
            'user_id' => $mechanic->id,
            'shop_name' => $name.' Repair',
            'service_address' => 'Calapan City',
            'is_verified' => false,
            'is_online' => false,
        ]);

        return $mechanic;
    }
}
