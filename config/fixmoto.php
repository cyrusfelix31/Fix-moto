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
        'Flat tire',
        'Battery problem',
        'Brake problem',
        'Engine problem',
        'Electrical problem',
        'Chain problem',
        'Other mechanical issue',
    ],
];
