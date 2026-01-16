<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | 配置CORS允许前端访问API
    |
    */

    'paths' => ['api/*', 'ai/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://app.yuzhen-fitness.cn',
        'https://yuzhen-fitness.cn',
        'https://www.yuzhen-fitness.cn',
        'http://localhost:9000', // 本地开发
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
