<?php

namespace Database\Seeders;

use App\Models\Barangay;
use App\Models\MechanicProfile;
use App\Services\BarangayLocator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CalapanBarangaySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(BarangayLocator $locator): void
    {
        $sourcePath = database_path('data/geo/calapan_barangays.geojson');
        $source = json_decode(file_get_contents($sourcePath), true, 512, JSON_THROW_ON_ERROR);
        $features = $source['features'] ?? [];
        $psgcCodes = array_map(fn (array $feature): string => (string) $feature['properties']['adm4_psgc'], $features);

        if (count($features) !== 62 || count(array_unique($psgcCodes)) !== 62) {
            throw new RuntimeException('Calapan barangay source must contain 62 unique PSGC features.');
        }

        DB::transaction(function () use ($features, $locator): void {
            $now = now();
            $barangays = [];

            foreach ($features as $feature) {
                if (($feature['properties']['geo_level'] ?? null) !== 'Bgy'
                    || (int) ($feature['properties']['adm3_psgc'] ?? 0) !== 1705205000) {
                    throw new RuntimeException('The barangay source contains an unexpected administrative feature.');
                }

                $center = $this->centroid($feature['geometry']);
                $psgcCode = (string) $feature['properties']['adm4_psgc'];

                $barangays[] = [
                    'psgc_code' => $psgcCode,
                    'name' => $feature['properties']['adm4_en'],
                    'latitude' => $center['latitude'],
                    'longitude' => $center['longitude'],
                    'boundary_geojson' => json_encode($feature['geometry'], JSON_THROW_ON_ERROR),
                    'geojson_reference' => 'database/data/geo/calapan_barangays.geojson#'.$psgcCode,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            Barangay::query()->upsert(
                $barangays,
                ['psgc_code'],
                ['name', 'latitude', 'longitude', 'boundary_geojson', 'geojson_reference', 'updated_at'],
            );

            $this->assignExistingProfiles($locator);
        });
    }

    /**
     * @param  array<string, mixed>  $geometry
     * @return array{latitude: float, longitude: float}
     */
    private function centroid(array $geometry): array
    {
        $polygons = $geometry['type'] === 'Polygon' ? [$geometry['coordinates']] : $geometry['coordinates'];
        $weightedLongitude = 0.0;
        $weightedLatitude = 0.0;
        $totalArea = 0.0;

        foreach ($polygons as $rings) {
            $ring = $rings[0];
            $crossSum = 0.0;
            $longitudeSum = 0.0;
            $latitudeSum = 0.0;

            for ($index = 0, $previous = count($ring) - 1; $index < count($ring); $previous = $index++) {
                [$longitude, $latitude] = $ring[$index];
                [$previousLongitude, $previousLatitude] = $ring[$previous];
                $cross = $previousLongitude * $latitude - $longitude * $previousLatitude;
                $crossSum += $cross;
                $longitudeSum += ($previousLongitude + $longitude) * $cross;
                $latitudeSum += ($previousLatitude + $latitude) * $cross;
            }

            if (abs($crossSum) < 1.0E-12) {
                continue;
            }

            $area = abs($crossSum / 2);
            $weightedLongitude += ($longitudeSum / (3 * $crossSum)) * $area;
            $weightedLatitude += ($latitudeSum / (3 * $crossSum)) * $area;
            $totalArea += $area;
        }

        if ($totalArea === 0.0) {
            throw new RuntimeException('A Calapan barangay boundary has no usable area.');
        }

        return [
            'latitude' => $weightedLatitude / $totalArea,
            'longitude' => $weightedLongitude / $totalArea,
        ];
    }

    private function assignExistingProfiles(BarangayLocator $locator): void
    {
        $barangays = Barangay::query()->get(['id', 'name', 'boundary_geojson']);

        MechanicProfile::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('id')
            ->chunkById(100, function (Collection $profiles) use ($barangays, $locator): void {
                foreach ($profiles as $profile) {
                    $match = $locator->locate((float) $profile->latitude, (float) $profile->longitude, $barangays);
                    $profile->update([
                        'barangay_id' => $match['barangay_id'],
                        'barangay' => $match['barangay_name'],
                        'barangay_match_status' => $match['status'],
                    ]);
                }
            });
    }
}
