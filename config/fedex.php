<?php

declare(strict_types=1);

return [
    'driver' => 'fedex',

    /*
    |--------------------------------------------------------------------------
    | FedEx REST API
    |--------------------------------------------------------------------------
    | Live:    https://apis.fedex.com
    | Sandbox: https://apis-sandbox.fedex.com
    */
    'base_url' => env('FEDEX_BASE_URL', 'https://apis.fedex.com'),
    'timeout' => (int) env('FEDEX_TIMEOUT', 60),

    'client_id' => env('FEDEX_CLIENT_ID'),
    'client_secret' => env('FEDEX_CLIENT_SECRET'),
    'account_number' => env('FEDEX_ACCOUNT_NUMBER'),

    /** Optional pre-issued OAuth access token (skips /oauth/token). */
    'token' => env('FEDEX_TOKEN'),

    'service_type' => env('FEDEX_SERVICE_TYPE', 'INTERNATIONAL_PRIORITY'),
    'packaging_type' => env('FEDEX_PACKAGING_TYPE', 'YOUR_PACKAGING'),
    'pickup_type' => env('FEDEX_PICKUP_TYPE', 'DROPOFF_AT_FEDEX_LOCATION'),
    'payment_type' => env('FEDEX_PAYMENT_TYPE', 'SENDER'),
    'label_image_type' => env('FEDEX_LABEL_IMAGE_TYPE', 'PDF'),
    'label_stock_type' => env('FEDEX_LABEL_STOCK_TYPE', 'PAPER_85X11_TOP_HALF_LABEL'),
    'weight_units' => env('FEDEX_WEIGHT_UNITS', 'KG'),
    'currency' => env('FEDEX_CURRENCY', 'USD'),

    'tracking_url_template' => env('FEDEX_TRACKING_URL_TEMPLATE', 'https://www.fedex.com/fedextrack/?trknbr={tracking}'),

    'status_map' => [
        'OC' => 'created',
        'PU' => 'picked_up',
        'IT' => 'in_transit',
        'AR' => 'in_transit',
        'DP' => 'in_transit',
        'OD' => 'out_for_delivery',
        'DL' => 'delivered',
        'DE' => 'delivered',
        'RS' => 'returned',
        'SE' => 'exception',
        'CA' => 'exception',
        'CREATED' => 'created',
        'PICKED UP' => 'picked_up',
        'IN TRANSIT' => 'in_transit',
        'OUT FOR DELIVERY' => 'out_for_delivery',
        'DELIVERED' => 'delivered',
        'RETURNED' => 'returned',
        'EXCEPTION' => 'exception',
    ],
];
