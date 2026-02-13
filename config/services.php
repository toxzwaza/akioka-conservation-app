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
        'scheme' => 'https',
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
    | 外部システムAPI（ユーザー・部品検索）
    |--------------------------------------------------------------------------
    | 別システムのAPIベースURL。検索時は ?q=キーワード 等でクエリを付与してください。
    */
    'external_api' => [
        'user_search_url' => env('EXTERNAL_API_USER_SEARCH_URL'),
        'part_search_url' => env('EXTERNAL_API_PART_SEARCH_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Conservation API（別システム連携）
    |--------------------------------------------------------------------------
    | 物品・在庫格納先・ユーザーを扱うREST APIのベースURL
    */
    'conservation_api' => [
        'base_url' => env('CONSERVATION_API_BASE_URL', 'https://akioka.cloud/api'),
    ],

];
