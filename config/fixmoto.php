<?php

return [
    'dispatch_radius' => (float) env('FIXMOTO_DISPATCH_RADIUS_KM', 25),
    'dispatch_timeout' => (int) env('FIXMOTO_DISPATCH_TIMEOUT_MINUTES', 120),
    'response_timeout' => (int) env('FIXMOTO_RESPONSE_TIMEOUT_SECONDS', 1200),
    'vehicle_categories' => [
        'two_wheeled' => 'Two-wheeled',
        'four_wheeled' => 'Four-wheeled',
    ],
    'services' => [
        'Flat Tire Repair',
        'Tire Replacement',
        'Battery Replacement',
        'Battery Jump-start',
        'Brake Repair',
        'Engine Repair',
        'Electrical Repair',
        'Chain Repair and Adjustment',
        'Change Oil',
        'Spark Plug Replacement',
        'Preventive Maintenance',
        'General Motorcycle Repair',
        // Retained so existing requests and rider bookmarks remain valid.
        'Flat tire',
        'Battery problem',
        'Brake problem',
        'Engine problem',
        'Electrical problem',
        'Chain problem',
        'Other mechanical issue',
    ],
];
