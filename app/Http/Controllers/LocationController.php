<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Services\BarangayLocator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class LocationController extends Controller
{
    public function search(
        Request $request,
        BarangayLocator $locator
    ) {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:150'],
        ]);

        $query = trim($data['q']);
        $results = collect();

        // Search existing local barangay records.
        $barangays = Barangay::query()->get();

        foreach ($barangays as $barangay) {

            $name = $barangay->getAttribute('name')
                ?? $barangay->getAttribute('barangay_name');

            if (!$name) {
                continue;
            }

            $address = $name
                .', Calapan City, Oriental Mindoro';

            if (stripos($address, $query) !== false) {

                $results->push([
                    'address' => $address,
                    'barangay' => $name,
                    'latitude' => null,
                    'longitude' => null,
                    'type' => 'barangay',
                ]);
            }
        }

        // Search detailed addresses through Geoapify.
        $apiKey = config('services.geoapify.key');

        if ($apiKey) {

            try {

                $response = Http::timeout(8)->get(
                    'https://api.geoapify.com/v1/geocode/autocomplete',
                    [
                        'text' => $query.', Calapan City, Philippines',
                        'format' => 'json',
                        'limit' => 15,
                        'filter' => 'rect:121.1,13.1,121.5,13.7',
                        'bias' => 'proximity:121.1803,13.4112',
                        'apiKey' => $apiKey,
                    ]
                );

                if ($response->successful()) {

                    foreach (
                        $response->json('results', [])
                        as $place
                    ) {

                        if (!isset($place['lat'], $place['lon'])) {
                            continue;
                        }

                        $latitude = (float) $place['lat'];
                        $longitude = (float) $place['lon'];

                        $match = $locator->locate(
                            $latitude,
                            $longitude
                        );

                        if (!$match['within_city']) {
                            continue;
                        }

                        $results->push([
                            'address' => $place['formatted']
                                ?? 'Calapan City',

                            'barangay' => $match['barangay_name']
                                ?? null,

                            'latitude' => $latitude,

                            'longitude' => $longitude,

                            'type' => 'address',
                        ]);
                    }
                }

            } catch (\Exception $exception) {

                // Local barangay suggestions remain available.

            }
        }

        return response()->json(
            $results
                ->unique('address')
                ->take(15)
                ->values()
        );
    }

    public function reverse(
        Request $request,
        BarangayLocator $locator
    ) {
        $data = $request->validate([
            'latitude' => [
                'required',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                'required',
                'numeric',
                'between:-180,180',
            ],
        ]);

        $latitude = (float) $data['latitude'];
        $longitude = (float) $data['longitude'];

        $match = $locator->locate(
            $latitude,
            $longitude
        );

        if (!$match['within_city']) {

            return response()->json([
                'message' => 'Select a location within Calapan City.',
            ], 422);
        }

        $barangay = $match['barangay_name'] ?? null;

        $address = implode(', ', array_filter([
            $barangay,
            'Calapan City',
            'Oriental Mindoro',
            'Philippines',
        ]));

        $apiKey = config('services.geoapify.key');

        if ($apiKey) {

            try {

                $response = Http::timeout(8)->get(
                    'https://api.geoapify.com/v1/geocode/reverse',
                    [
                        'lat' => $latitude,
                        'lon' => $longitude,
                        'format' => 'json',
                        'apiKey' => $apiKey,
                    ]
                );

                if ($response->successful()) {

                    $address = $response->json(
                        'results.0.formatted'
                    ) ?? $address;
                }

            } catch (\Exception $exception) {

                // Use local barangay address as fallback.

            }
        }

        return response()->json([
            'address' => $address,
            'barangay' => $barangay,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
    }
}
