<?php

return [
    'super_admin' => [
        'name' => env('SUPER_ADMIN_NAME', 'Super Admin'),
        'email' => env('SUPER_ADMIN_EMAIL', 'admin@school.test'),
        'phone' => env('SUPER_ADMIN_PHONE', '01700000000'),
        'password' => env('SUPER_ADMIN_PASSWORD', 'ChangeMe!2026'),
    ],

    'seed_demo_data' => (bool) env('SEED_DEMO_DATA', false),

    'admission_default_password' => env('ADMISSION_DEFAULT_PASSWORD', 'ChangeMe!2026'),
];
