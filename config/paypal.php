<?php
return [
    'mode'    => env('PAYPAL_MODE', 'sandbox'),
    'sandbox' => [
        'client_id'         => env('PAYPAL_SANDBOX_CLIENT_ID'),
        'client_secret'     => env('PAYPAL_SANDBOX_CLIENT_SECRET'),
        'app_id'            => '',
    ],
    'live' => [
        'client_id'         => '',
        'client_secret'     => '',
        'app_id'            => '',
    ],
    'currency' => env('PAYPAL_CURRENCY', 'USD'),
];
