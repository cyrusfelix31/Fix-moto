<?php

namespace App\Services;

use App\Models\MechanicProfile;

class ShopServiceCatalog
{
    public const LABELS = [
        'Flat tire' => 'Flat Tire Repair',
        'Battery problem' => 'Battery Repair',
        'Brake problem' => 'Brake Repair',
        'Engine problem' => 'Engine Repair',
        'Electrical problem' => 'Electrical Repair',
        'Chain problem' => 'Chain Repair and Adjustment',
        'Other mechanical issue' => 'General Mechanical Repair',
    ];

    public static function normalize(array $services): array
    {
        $result = [];

        foreach ($services as $service) {
            if (!is_string($service)) {
                continue;
            }

            $service = trim($service);

            if ($service === '') {
                continue;
            }

            foreach (self::LABELS as $key => $label) {
                if (
                    strcasecmp($service, $key) === 0 ||
                    strcasecmp($service, $label) === 0
                ) {
                    $service = $key;
                    break;
                }
            }

            $index = mb_strtolower($service);

            if (!isset($result[$index])) {
                $result[$index] = $service;
            }
        }

        return array_values($result);
    }

    public static function label(string $service): string
    {
        return self::LABELS[$service] ?? $service;
    }

    public static function supports(
        array $offered,
        array $requested
    ): bool {
        $offered = array_map(
            fn ($service) => mb_strtolower($service),
            self::normalize($offered)
        );

        $requested = array_map(
            fn ($service) => mb_strtolower($service),
            self::normalize($requested)
        );

        return $requested !== []
            && array_diff($requested, $offered) === [];
    }

    public static function riderOptions(): array
    {
        $services = config('fixmoto.services', []);

        $profiles = MechanicProfile::query()
            ->where('is_verified', true)
            ->where('verification_status', 'approved')
            ->where('is_online', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereHas('activeMembers')
            ->get(['services']);

        foreach ($profiles as $profile) {
            $services = array_merge(
                $services,
                $profile->services ?? []
            );
        }

        return self::normalize($services);
    }
}