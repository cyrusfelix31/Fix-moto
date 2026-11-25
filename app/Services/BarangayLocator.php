<?php

namespace App\Services;

use App\Models\Barangay;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use RuntimeException;

class BarangayLocator
{
    private ?array $cityGeometry = null;

    /**
     * @return array{within_city: bool, status: string, barangay_id: ?int, barangay_name: ?string}
     */
    public function locate(float $latitude, float $longitude, ?EloquentCollection $barangays = null): array
    {
        if ($this->pointRelation($this->cityGeometry(), $longitude, $latitude) === 0) {
            return ['within_city' => false, 'status' => 'unmatched', 'barangay_id' => null, 'barangay_name' => null];
        }

        $matches = [];
        $touchesBoundary = false;

        foreach ($barangays ?? Barangay::query()->get(['id', 'name', 'boundary_geojson']) as $barangay) {
            $relation = $this->pointRelation($barangay->boundary_geojson, $longitude, $latitude);

            if ($relation === 1) {
                $matches[] = $barangay;
            } elseif ($relation === 2) {
                $touchesBoundary = true;
            }
        }

        if (count($matches) === 1 && ! $touchesBoundary) {
            return [
                'within_city' => true,
                'status' => 'matched',
                'barangay_id' => $matches[0]->id,
                'barangay_name' => $matches[0]->name,
            ];
        }

        return [
            'within_city' => true,
            'status' => count($matches) > 1 || $touchesBoundary ? 'ambiguous' : 'unmatched',
            'barangay_id' => null,
            'barangay_name' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function cityFeature(): array
    {
        $path = database_path('data/geo/calapan_city_boundary.geojson');
        $feature = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        if (! isset($feature['geometry'])) {
            throw new RuntimeException('The Calapan City boundary data is invalid.');
        }

        return $feature;
    }

    private function cityGeometry(): array
    {
        return $this->cityGeometry ??= $this->cityFeature()['geometry'];
    }

    private function pointRelation(array $geometry, float $longitude, float $latitude): int
    {
        $polygons = match ($geometry['type'] ?? null) {
            'Polygon' => [$geometry['coordinates']],
            'MultiPolygon' => $geometry['coordinates'],
            default => throw new RuntimeException('Unsupported barangay boundary geometry.'),
        };
        $touchesBoundary = false;

        foreach ($polygons as $rings) {
            $outerRelation = $this->ringRelation($rings[0], $longitude, $latitude);

            if ($outerRelation === 0) {
                continue;
            }

            if ($outerRelation === 2) {
                $touchesBoundary = true;

                continue;
            }

            $insideHole = false;

            foreach (array_slice($rings, 1) as $hole) {
                $holeRelation = $this->ringRelation($hole, $longitude, $latitude);
                if ($holeRelation === 1) {
                    $insideHole = true;
                    break;
                }

                if ($holeRelation === 2) {
                    $touchesBoundary = true;
                    $insideHole = true;
                    break;
                }
            }

            if (! $insideHole) {
                return 1;
            }
        }

        return $touchesBoundary ? 2 : 0;
    }

    /**
     * Returns 0 outside, 1 inside, and 2 on the ring boundary.
     *
     * @param  array<int, array{0: float, 1: float}>  $ring
     */
    private function ringRelation(array $ring, float $longitude, float $latitude): int
    {
        $inside = false;
        $pointCount = count($ring);

        for ($index = 0, $previous = $pointCount - 1; $index < $pointCount; $previous = $index++) {
            [$currentLongitude, $currentLatitude] = $ring[$index];
            [$previousLongitude, $previousLatitude] = $ring[$previous];
            $crossProduct = ($longitude - $currentLongitude) * ($previousLatitude - $currentLatitude)
                - ($latitude - $currentLatitude) * ($previousLongitude - $currentLongitude);

            if (abs($crossProduct) < 1.0E-10
                && $longitude >= min($currentLongitude, $previousLongitude) - 1.0E-10
                && $longitude <= max($currentLongitude, $previousLongitude) + 1.0E-10
                && $latitude >= min($currentLatitude, $previousLatitude) - 1.0E-10
                && $latitude <= max($currentLatitude, $previousLatitude) + 1.0E-10) {
                return 2;
            }

            if (($currentLatitude > $latitude) !== ($previousLatitude > $latitude)
                && $longitude < ($previousLongitude - $currentLongitude) * ($latitude - $currentLatitude)
                / ($previousLatitude - $currentLatitude) + $currentLongitude) {
                $inside = ! $inside;
            }
        }

        return $inside ? 1 : 0;
    }
}
