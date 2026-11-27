<?php

namespace Database\Seeders;

use App\Models\DispatchSetting;
use App\Models\MechanicProfile;
use App\Models\SystemStatusIncident;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\BarangayLocator;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(BarangayLocator $locator): void
    {
        $this->call(CalapanBarangaySeeder::class);
        User::whereIn('email', ['admin@fixmoto.local', 'rider@fixmoto.local', 'mechanic@fixmoto.local'])->delete();

        $admin = User::updateOrCreate(
            ['email' => 'admin'],
            [
                'name' => 'FIXMOTO Administrator',
                'phone' => '09170000001',
                'role' => 'admin',
                'password' => Hash::make('12345'),
            ]);

        $rider = User::updateOrCreate(
            ['email' => 'rider'],
            [
                'name' => 'Juan Rider',
                'phone' => '09170000002',
                'role' => 'rider',
                'password' => Hash::make('12345'),
            ]);

        Vehicle::firstOrCreate(
            ['user_id' => $rider->id, 'plate_number' => 'ABC-1234'],
            [
                'brand' => 'Honda',
                'model' => 'Click 160',
                'year' => 2024,
                'vehicle_type' => 'Scooter',
                'vehicle_category' => 'two_wheeled',
                'notes' => 'Example development vehicle',
                'is_active' => true,
            ],
        );

        $mechanic = User::updateOrCreate(
            ['email' => 'mechanic'],
            [
                'name' => 'Marco Mechanic',
                'phone' => '09170000003',
                'role' => 'mechanic',
                'password' => Hash::make('12345'),
            ]);

        $mechanicProfile = MechanicProfile::updateOrCreate(
            ['user_id' => $mechanic->id],
            [
                'shop_name' => 'Marco Vehicle Repair',
                'service_address' => 'Calapan City, Oriental Mindoro',
                'barangay' => 'Camilmil',
                'services' => ['Flat tire', 'Battery problem', 'Brake problem', 'Engine problem', 'Electrical problem', 'Chain problem'],
                'coverage_radius_km' => 25,
                'latitude' => 13.4112,
                'longitude' => 121.1803,
                'is_verified' => true,
                'verification_status' => 'fully_verified',
                'is_online' => true,
                'last_location_at' => now(),
            ],
        );
        $match = $locator->locate((float) $mechanicProfile->latitude, (float) $mechanicProfile->longitude);
        $mechanicProfile->update([
            'barangay_id' => $match['barangay_id'],
            'barangay' => $match['barangay_name'],
            'barangay_match_status' => $match['status'],
        ]);
        $mechanicProfile->members()->firstOrCreate(
            ['name' => $mechanic->name],
            ['is_active' => true],
        );

        foreach ([['dispatch_radius', '25', 'Maximum dispatch radius in kilometers'], ['response_timeout', '90', 'Mechanic response timeout in seconds'], ['dispatch_timeout', '120', 'Overall dispatch timeout in minutes']] as [$key,$value,$description]) {
            DispatchSetting::firstOrCreate(['key' => $key], ['value' => $value, 'description' => $description, 'updated_by' => $admin->id]);
        }

        SystemStatusIncident::firstOrCreate(
            ['status' => 'operational', 'ended_at' => null],
            [
                'started_at' => now(),
                'reason' => 'System initialized',
                'recorded_by' => $admin->id,
            ],
        );
    }
}
