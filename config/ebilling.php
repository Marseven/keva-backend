<?php

return [
    /*
    |--------------------------------------------------------------------------
    | EBILLING Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration pour l'intégration avec le service de paiement EBILLING
    | de Digitech Africa
    |
    */

    'base_url' => env('EBILLING_BASE_URL', 'https://lab.billing-easy.net'),

    'username' => env('EBILLING_USERNAME'),

    'shared_key' => env('EBILLING_SHARED_KEY'),

    'redirect_url' => env('EBILLING_REDIRECT_URL', 'https://test.billing-easy.net'),

    'callback_url' => env('EBILLING_CALLBACK_URL'),

    'endpoints' => [
        'create_bill' => '/api/v1/merchant/e_bills',
        'ussd_push' => '/api/v1/merchant/e_bills/{bill_id}/ussd_push',
    ],

    'payment_methods' => [
        'airtel_money' => [
            'name' => 'airtelmoney',
            'prefix' => '07',
            'length' => 9,
        ],
        'moov_money' => [
            'name' => 'moovmoney4',
            'prefix' => '06',
            'length' => 9,
        ],
        'visa_mastercard' => [
            'name' => 'ORABANK_NG',
            'operator' => 'ORABANK_NG',
        ],
    ],

    'default_expiry_period' => 60, // minutes

    'currency' => 'XAF',

    'test_mode' => env('APP_ENV') !== 'production',

    'webhook_secret' => env('EBILLING_WEBHOOK_SECRET'),

    'timeout' => 30, // seconds

    'retry_attempts' => 3,
];
