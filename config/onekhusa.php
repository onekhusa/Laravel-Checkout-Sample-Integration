<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OneKhusa API Credentials
    |--------------------------------------------------------------------------
    | Sandbox / production credentials issued in the OneKhusa merchant portal.
    */

    'api_key' => env('ONEKHUSA_API_KEY'),
    'api_secret' => env('ONEKHUSA_API_SECRET'),
    'organisation_id' => env('ONEKHUSA_ORG_ID'),
    'merchant_account_number' => (int) env('ONEKHUSA_MERCHANT_NUMBER'),

    /*
    |--------------------------------------------------------------------------
    | Public Callback URL
    |--------------------------------------------------------------------------
    | A publicly reachable URL (e.g. an ngrok tunnel) that OneKhusa uses to
    | redirect customers back and to deliver webhook callbacks to this app.
    */

    'public_callback_url' => env('PUBLIC_CALLBACK_URL'),

    /*
    |--------------------------------------------------------------------------
    | API Endpoints
    |--------------------------------------------------------------------------
    */

    'endpoints' => [
        'get_access_token' => env('ONEKHUSA_TOKEN_URL', 'https://api.onekhusa.com/sandbox/v1/account/getAccessToken'),
        'checkout_initiate' => env('ONEKHUSA_CHECKOUT_URL', 'https://api.onekhusa.com/sandbox/v1/checkout/rtp/initiate'),
        'checkout_redirect' => env('ONEKHUSA_CHECKOUT_REDIRECT_URL', 'https://checkout.onekhusa.com/requestToPay/initiate'),
    ],

];
