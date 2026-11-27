<?php

return [
    'dispatch_radius' => (float) env('FIXMOTO_DISPATCH_RADIUS_KM', 25),
    'dispatch_timeout' => (int) env('FIXMOTO_DISPATCH_TIMEOUT_MINUTES', 120),
    'response_timeout' => (int) env('FIXMOTO_RESPONSE_TIMEOUT_SECONDS', 1200),
    'gps_arrival_radius_meters' => (float) env('GPS_ARRIVAL_RADIUS_METERS', 50),
    'gps_max_accuracy_meters' => (float) env('GPS_MAX_ACCURACY_METERS', 35),
    'gps_max_age_seconds' => (int) env('GPS_MAX_AGE_SECONDS', 120),
    'otp_length' => 6,
    'otp_expiration_minutes' => 5,
    'otp_resend_seconds' => 60,
    'otp_max_attempts' => 5,
    'document_expiration_max_years' => (int) env('FIXMOTO_DOCUMENT_EXPIRATION_MAX_YEARS', 20),
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
