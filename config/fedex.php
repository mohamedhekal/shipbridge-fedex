<?php

declare(strict_types=1);

return [
    'driver' => 'fedex',
    'base_url' => env('FEDEX_BASE_URL', 'https://apis.fedex.com'),
    'timeout' => (int) env('FEDEX_TIMEOUT', 20),
    'client_id' => env('FEDEX_CLIENT_ID'),
    'client_secret' => env('FEDEX_CLIENT_SECRET'),
    'token' => env('FEDEX_TOKEN'),
    'status_map' => [
        'OC' => 'created',
        'PU' => 'picked_up',
        'IT' => 'in_transit',
        'OD' => 'out_for_delivery',
        'DL' => 'delivered',
        'RS' => 'returned',
        'DE' => 'exception',
    ],
];
