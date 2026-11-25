<?php

namespace Tests\Feature;

use App\Models\MechanicProfile;
use App\Models\Barangay;
use App\Models\User;
use Database\Seeders\CalapanBarangaySeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dashboard_geography_includes_all_barangays_and_filters_shops_by_selection(): void
    {
        $this->seed(CalapanBarangaySeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);
        $barangay = Barangay::query()->where('psgc_code', '1705205016')->firstOrFail();
        $baselineShopCount = MechanicProfile::query()->count();
        $baselineCoveredBarangayCount = Barangay::query()->has('mechanicProfiles')->count();
        $baselineOnlineCount = MechanicProfile::query()
            ->where('is_verified', true)
            ->where('is_online', true)
            ->whereHas('activeMembers')
            ->count();
        $baselineSelectedShopCount = MechanicProfile::query()->where('barangay_id', $barangay->id)->count();
        $baselineSelectedOnlineCount = MechanicProfile::query()
            ->where('barangay_id', $barangay->id)
            ->where('is_verified', true)
            ->where('is_online', true)
            ->whereHas('activeMembers')
            ->count();
        $onlineShop = User::factory()->create(['role' => 'mechanic']);
        $onlineProfile = MechanicProfile::factory()->for($onlineShop, 'mechanic')->create([
            'barangay_id' => $barangay->id,
            'barangay' => $barangay->name,
            'barangay_match_status' => 'matched',
            'is_verified' => true,
            'is_online' => true,
        ]);
        $onlineProfile->members()->create(['name' => 'Available mechanic', 'is_active' => true]);
        $offlineShop = User::factory()->create(['role' => 'mechanic']);
        MechanicProfile::factory()->for($offlineShop, 'mechanic')->create([
            'barangay_id' => $barangay->id,
            'barangay' => $barangay->name,
            'barangay_match_status' => 'matched',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard.geography'));

        $response->assertOk()
            ->assertJsonCount(62, 'barangays')
            ->assertJsonCount(62, 'chart')
            ->assertJsonCount(62, 'features.features')
            ->assertJsonPath('summary.registered_shops', $baselineShopCount + 2)
            ->assertJsonPath('summary.covered_barangays', $baselineCoveredBarangayCount + ($baselineSelectedShopCount === 0 ? 1 : 0))
            ->assertJsonPath('summary.online_shops', $baselineOnlineCount + 1)
            ->assertJsonPath('summary.offline_shops', $baselineShopCount - $baselineOnlineCount + 1)
            ->assertJsonPath('barangays.0.name', 'Balingayan');

        $selectedResponse = $this->actingAs($admin)->get(route('admin.dashboard.geography', [
            'barangay_id' => $barangay->id,
        ]));

        $selectedResponse->assertOk()
            ->assertJsonCount(1, 'chart')
            ->assertJsonPath('selected.name', 'Camilmil')
            ->assertJsonPath('selected.shop_count', $baselineSelectedShopCount + 2)
            ->assertJsonPath('selected.online_count', $baselineSelectedOnlineCount + 1)
            ->assertJsonPath('selected.offline_count', $baselineSelectedShopCount - $baselineSelectedOnlineCount + 1)
            ->assertJsonPath('chart.0.shop_count', $baselineSelectedShopCount + 2)
            ->assertJsonPath('chart.0.online_count', $baselineSelectedOnlineCount + 1)
            ->assertJsonPath('chart.0.offline_count', $baselineSelectedShopCount - $baselineSelectedOnlineCount + 1);

        $emptyBarangay = Barangay::query()->whereDoesntHave('mechanicProfiles')->firstOrFail();
        $emptyResponse = $this->actingAs($admin)->getJson(route('admin.dashboard.geography', [
            'barangay_id' => $emptyBarangay->id,
        ]));

        $emptyResponse->assertOk()
            ->assertJsonCount(1, 'chart')
            ->assertJsonPath('selected.name', $emptyBarangay->name)
            ->assertJsonPath('selected.shop_count', 0)
            ->assertJsonPath('chart.0.shop_count', 0)
            ->assertJsonPath('chart.0.online_count', 0)
            ->assertJsonPath('chart.0.offline_count', 0);
    }
}
