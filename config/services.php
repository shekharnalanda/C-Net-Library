<?php

return [
    'mci_central' => [
        'enabled' => filter_var(env('MCI_CENTRAL_SYNC_ENABLED', false), FILTER_VALIDATE_BOOL),
        'url' => rtrim((string) env('MCI_CENTRAL_URL', 'https://mciedu.in'), '/'),
        'token' => env('MCI_CENTRAL_TOKEN'),
        'business_code' => env('MCI_CENTRAL_BUSINESS_CODE', 'c-net-library'),
        'timeout' => (int) env('MCI_CENTRAL_TIMEOUT', 10),
    ],
];
