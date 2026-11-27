<?php

namespace App\Services;

use App\Models\MechanicProfile;
use App\Models\MechanicShopService;

class ShopServiceMatcher
{
    private const ALIASES = [
        'Flat tire' => ['Flat Tire Repair'],
        'Battery problem' => ['Battery Replacement', 'Battery Jump-start'],
        'Brake problem' => ['Brake Repair'],
        'Engine problem' => ['Engine Repair', 'General Motorcycle Repair'],
        'Electrical problem' => ['Electrical Repair'],
        'Chain problem' => ['Chain Repair and Adjustment'],
        'Other mechanical issue' => ['General Motorcycle Repair'],
    ];

    public static function isQualified(MechanicProfile $profile, array $requested): bool
    {
        $enabled = $profile->shopServices()
            ->where('is_enabled', true)
            ->pluck('name')
            ->map(fn (string $name) => mb_strtolower($name))
            ->all();

        // Compatibility for older records created before shop services were introduced.
        if ($enabled === [] && ! $profile->shopServices()->exists()) {
            $legacy = $profile->services ?? [];
            if ($legacy === []) {
                $legacy = MechanicShopService::DEFAULT_SERVICES;
            }
            foreach ($legacy as $service) {
                foreach (self::ALIASES[$service] ?? [$service] as $match) {
                    $enabled[] = mb_strtolower($match);
                }
            }
        }

        foreach ($requested as $service) {
            $matches = self::ALIASES[$service] ?? [$service];
            foreach ($matches as $match) {
                if (in_array(mb_strtolower($match), $enabled, true)) {
                    return true;
                }
            }
        }

        return false;
    }
}
