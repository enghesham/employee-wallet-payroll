<?php

return [
    'provider_tokens' => [
        'payroll' => env('PAYROLL_PROVIDER_TOKEN', 'local-payroll-token'),
        'bank' => env('BANK_PROVIDER_TOKEN', 'local-bank-token'),
    ],
];
