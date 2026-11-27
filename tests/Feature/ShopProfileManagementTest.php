<?php

namespace Tests\Feature;

use App\Models\MechanicProfile;
use App\Models\User;
use App\Services\ShopServiceMatcher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ShopProfileManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_verified_owner_sees_their_shop_owner_team_and_service_profiles(): void
    {
        [$owner, $profile] = $this->createShop();
        $profile->members()->create(['name' => 'Mika Santos', 'phone' => '09171234567', 'specialization' => 'Brake repair']);
        $profile->shopServices()->create(['name' => 'Brake Repair', 'is_enabled' => true]);

        $this->actingAs($owner)->get(route('mechanic.shop-profile'))
            ->assertOk()
            ->assertSee('Shop location and contact')
            ->assertSee("Owner's profile", false)
            ->assertSee('Mika Santos')
            ->assertSee('Brake Repair')
            ->assertSee('shop-profile-map', false);
    }

    public function test_owner_can_create_edit_and_remove_mechanics_and_services(): void
    {
        [$owner, $profile] = $this->createShop();

        $this->actingAs($owner)->post(route('mechanic.team.store'), [
            'name' => 'Rafi Cruz', 'phone' => '09175550123', 'specialization' => 'Electrical repair', 'years_experience' => 4,
        ])->assertRedirect();

        $member = $profile->members()->where('name', 'Rafi Cruz')->firstOrFail();
        $this->actingAs($owner)->put(route('mechanic.team.update', $member), [
            'name' => 'Rafi Cruz', 'phone' => '09175550124', 'specialization' => 'Wiring', 'years_experience' => 5, 'is_active' => 1,
        ])->assertRedirect();
        $this->assertSame('Wiring', $member->fresh()->specialization);

        $this->actingAs($owner)->post(route('mechanic.shop-services.store'), ['name' => 'Wheel alignment'])
            ->assertRedirect();
        $service = $profile->shopServices()->where('name', 'Wheel alignment')->firstOrFail();
        $this->actingAs($owner)->put(route('mechanic.shop-services.update', $service), ['name' => 'Wheel balancing'])
            ->assertRedirect();
        $service->refresh();
        $this->assertSame('Wheel balancing', $service->name);
        $this->actingAs($owner)->post(route('mechanic.shop-services.toggle', $service))->assertRedirect();
        $this->assertFalse($service->fresh()->is_enabled);
        $this->actingAs($owner)->delete(route('mechanic.shop-services.destroy', $service))->assertRedirect();
        $this->actingAs($owner)->delete(route('mechanic.team.destroy', $member))->assertRedirect();
        $this->assertDatabaseMissing('mechanic_shop_members', ['id' => $member->id]);
    }

    public function test_shop_service_matching_only_accepts_enabled_qualifying_services(): void
    {
        [$owner, $profile] = $this->createShop();
        $service = $profile->shopServices()->create(['name' => 'Brake Repair', 'is_enabled' => false]);

        $this->assertFalse(ShopServiceMatcher::isQualified($profile, ['Brake problem']));
        $service->update(['is_enabled' => true]);
        $this->assertTrue(ShopServiceMatcher::isQualified($profile, ['Brake problem']));
    }

    public function test_owner_cannot_change_another_shops_mechanic_or_service(): void
    {
        [$owner] = $this->createShop();
        [, $otherProfile] = $this->createShop();
        $member = $otherProfile->members()->create(['name' => 'Private mechanic']);
        $service = $otherProfile->shopServices()->create(['name' => 'Private service']);

        $this->actingAs($owner)->put(route('mechanic.team.update', $member), [
            'name' => 'Changed', 'is_active' => 1,
        ])->assertNotFound();
        $this->actingAs($owner)->delete(route('mechanic.shop-services.destroy', $service))->assertNotFound();
    }

    /** @return array{User, MechanicProfile} */
    private function createShop(): array
    {
        $owner = User::factory()->create(['role' => 'mechanic']);
        $profile = MechanicProfile::factory()->for($owner, 'mechanic')->create([
            'shop_name' => 'Calapan Repair',
            'is_verified' => true,
            'verification_status' => 'approved',
        ]);

        return [$owner, $profile];
    }
}
