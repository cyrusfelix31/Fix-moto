<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\MechanicProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_action_is_recorded_with_user_role_and_details(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'rider']);

        $response = $this->actingAs($admin)->post(route('admin.users.suspend', $user));

        $response->assertRedirect();
        $this->assertDatabaseHas('audit_logs', [
            'admin_id' => $admin->id,
            'user_id' => $user->id,
            'role' => 'rider',
            'action' => 'user.suspension_updated',
        ]);

        $auditLog = AuditLog::query()
            ->where('admin_id', $admin->id)
            ->where('user_id', $user->id)
            ->where('action', 'user.suspension_updated')
            ->firstOrFail();

        $this->assertSame(['is_suspended' => true], $auditLog->details);
    }

    public function test_admin_audit_trail_shows_timestamp_admin_user_role_action_and_details(): void
    {
        $this->travelTo(Carbon::parse('2026-01-05 12:00:00'));
        $admin = User::factory()->create(['name' => 'Audit Administrator', 'role' => 'admin']);
        $user = User::factory()->create([
            'name' => '<script>alert(1)</script>',
            'role' => 'mechanic',
        ]);
        AuditLog::record($admin, 'user.suspension_updated', $user, ['is_suspended' => true]);

        $response = $this->actingAs($admin)->get(route('admin.audit-trail'));

        $response->assertOk()
            ->assertSee('Timestamp')
            ->assertSee('Admin')
            ->assertSee('User')
            ->assertSee('Role')
            ->assertSee('Action')
            ->assertSee('Details')
            ->assertSee('2026-01-05 12:00:00')
            ->assertSee('Audit Administrator')
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('Mechanic')
            ->assertSee('User suspension updated')
            ->assertSee('Is suspended:')
            ->assertSee('Yes');
    }

    #[DataProvider('unprivilegedRoles')]
    public function test_non_admin_role_is_forbidden_from_viewing_admin_audit_trail(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);

        $response = $this->actingAs($user)->get(route('admin.audit-trail'));

        $response->assertForbidden();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unprivilegedRoles(): array
    {
        return [
            'rider' => ['rider'],
            'mechanic' => ['mechanic'],
        ];
    }

    public function test_successful_user_login_is_recorded_with_role(): void
    {
        $user = User::factory()->create(['role' => 'rider']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('rider.dashboard'));
        $this->assertDatabaseHas('audit_logs', [
            'admin_id' => null,
            'user_id' => $user->id,
            'role' => 'rider',
            'action' => 'user.logged_in',
        ]);
    }

    public function test_rider_request_creation_is_recorded_with_its_reference(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        $vehicle = $rider->vehicles()->create([
            'brand' => 'Honda',
            'model' => 'Click',
            'year' => 2024,
            'vehicle_type' => 'Scooter',
        ]);

        $response = $this->actingAs($rider)->post(route('rider.requests.store'), [
            'vehicle_id' => $vehicle->id,
            'issue_category' => 'Flat tire',
            'problem_description' => 'The rear tire is flat.',
            'latitude' => '13.4112',
            'longitude' => '121.1803',
        ]);

        $response->assertRedirect();
        $serviceRequest = $rider->riderRequests()->latest()->firstOrFail();
        $auditLog = AuditLog::query()
            ->where('user_id', $rider->id)
            ->where('action', 'service_request.created')
            ->firstOrFail();

        $this->assertSame('rider', $auditLog->role);
        $this->assertSame($serviceRequest->reference, $auditLog->details['reference']);
        $this->assertSame($serviceRequest->dispatch_status, $auditLog->details['dispatch_status']);
    }

    public function test_mechanic_job_status_update_is_recorded_with_the_new_status(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        $mechanic = User::factory()->create(['role' => 'mechanic']);
        $mechanicProfile = MechanicProfile::factory()->for($mechanic, 'mechanic')->create();
        $mechanicShopMember = $mechanicProfile->members()->create(['name' => 'Assigned Mechanic']);
        $vehicle = $rider->vehicles()->create([
            'brand' => 'Honda',
            'model' => 'Click',
            'year' => 2024,
            'vehicle_type' => 'Scooter',
        ]);
        $serviceRequest = $rider->riderRequests()->create([
            'reference' => 'FM-AUDIT-STATUS',
            'vehicle_id' => $vehicle->id,
            'mechanic_id' => $mechanic->id,
            'mechanic_shop_member_id' => $mechanicShopMember->id,
            'issue_category' => 'Flat tire',
            'problem_description' => 'The rear tire is flat.',
            'latitude' => 13.4112,
            'longitude' => 121.1803,
            'dispatch_status' => 'assigned',
            'service_status' => 'accepted',
        ]);

        $response = $this->actingAs($mechanic)->post(route('mechanic.requests.status', $serviceRequest), [
            'status' => 'on_the_way',
        ]);

        $response->assertRedirect();
        $auditLog = AuditLog::query()
            ->where('user_id', $mechanic->id)
            ->where('action', 'mechanic.job_status_updated')
            ->firstOrFail();

        $this->assertSame('mechanic', $auditLog->role);
        $this->assertSame('FM-AUDIT-STATUS', $auditLog->details['service_request_reference']);
        $this->assertSame('on_the_way', $auditLog->details['status']);
    }
}
