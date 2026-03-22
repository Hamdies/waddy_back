<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Apple Push Notification service (APNs) — for iOS Live Activities
    |--------------------------------------------------------------------------
    |
    | To enable iOS Live Activity background updates, set these values
    | in your .env file. You'll need an APNs Auth Key (.p8) from
    | Apple Developer → Keys → Configure.
    |
    */
    'apns' => [
        'team_id'     => env('APNS_TEAM_ID'),
        'key_id'      => env('APNS_KEY_ID'),
        'private_key' => env('APNS_PRIVATE_KEY_PATH')
            ? file_get_contents(env('APNS_PRIVATE_KEY_PATH'))
            : null,
        'bundle_id'   => env('APNS_BUNDLE_ID', 'com.waddy.app'),
        'environment' => env('APNS_ENVIRONMENT', 'sandbox'),
    ],

];
