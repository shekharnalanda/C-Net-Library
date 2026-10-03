<?php
return [
    'enabled' => env('MCI_PAY_ENABLED', false),
    'base_url' => 'https://pay.mciedu.com',
    'integration' => 'library',
    'secret' => env('MCI_PAY_SECRET'),
];

