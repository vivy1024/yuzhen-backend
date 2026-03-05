<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | 配置CORS允许前端访问API
    | 安全加固：使用明确的域名白名单和请求头列表，不使用通配符
    |
    */

    'paths' => ['api/*', 'ai/*', 'sanctum/csrf-cookie'],

    // 明确的HTTP方法列表，不使用通配符
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // 根据环境区分允许的域名
    // 生产环境：仅允许yuzhen-fitness.cn及其子域名 + Capacitor移动端
    // 开发环境：允许localhost相关域名
    'allowed_origins' => config('app.env') === 'production'
        ? [
            'https://app.yuzhen-fitness.cn',
            'https://yuzhen-fitness.cn',
            'https://www.yuzhen-fitness.cn',
            'capacitor://localhost',
            'https://localhost',
        ]
        : [
            'http://localhost:9000',
            'http://127.0.0.1:9000',
            'http://localhost:5173',
            'http://127.0.0.1:5173',
            'http://localhost:3000',
            'http://127.0.0.1:3000',
            'capacitor://localhost',
            'https://localhost',
        ],

    // 不使用模式匹配，避免反射攻击
    'allowed_origins_patterns' => [],

    // 明确允许的请求头，不使用通配符
    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'X-Requested-With',
        'X-Internal-Token',
        'Accept',
        'Origin',
        'X-CSRF-TOKEN',
    ],

    'exposed_headers' => [],

    // 预检请求缓存时间（秒）
    'max_age' => 7200,

    'supports_credentials' => true,

];
