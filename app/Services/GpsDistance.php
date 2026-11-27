<?php

namespace App\Services;

class GpsDistance
{
    public static function meters(float $latitudeA, float $longitudeA, float $latitudeB, float $longitudeB): float
    {
        $earthRadiusMeters = 6_371_000;
        $latDifference = deg2rad($latitudeB - $latitudeA);
        $lonDifference = deg2rad($longitudeB - $longitudeA);
        $a = sin($latDifference / 2) ** 2
            + cos(deg2rad($latitudeA)) * cos(deg2rad($latitudeB)) * sin($lonDifference / 2) ** 2;

        return $earthRadiusMeters * 2 * atan2(sqrt($a), sqrt(max(0, 1 - $a)));
    }
}
