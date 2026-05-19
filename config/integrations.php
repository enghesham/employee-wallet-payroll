<?php

return [
    'provider_tokens' => [
        'payroll' => env('PAYROLL_PROVIDER_TOKEN'),
        'bank' => env('BANK_PROVIDER_TOKEN'),
    ],

    'webhook_secrets' => [
        'payroll' => env('PAYROLL_WEBHOOK_SECRET'),
        'bank' => env('BANK_WEBHOOK_SECRET'),
    ],

    'webhook_signature_tolerance_seconds' => env('WEBHOOK_SIGNATURE_TOLERANCE_SECONDS', 300),
];
