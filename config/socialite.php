<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 社交登录配置
    |--------------------------------------------------------------------------
    |
    | 配置各个社交平台的AppID和AppSecret
    |
    */
    
    'wechat' => [
        'client_id' => env('WECHAT_APP_ID'),
        'client_secret' => env('WECHAT_APP_SECRET'),
        'redirect' => env('WECHAT_REDIRECT_URI', env('APP_URL') . '/api/social/callback/wechat'),
    ],
    
    'weibo' => [
        'client_id' => env('WEIBO_CLIENT_ID'),
        'client_secret' => env('WEIBO_CLIENT_SECRET'),
        'redirect' => env('WEIBO_REDIRECT_URI', env('APP_URL') . '/api/social/callback/weibo'),
    ],
    
    'qq' => [
        'client_id' => env('QQ_CLIENT_ID'),
        'client_secret' => env('QQ_CLIENT_SECRET'),
        'redirect' => env('QQ_REDIRECT_URI', env('APP_URL') . '/api/social/callback/qq'),
    ],
    
    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT_URI', env('APP_URL') . '/api/social/callback/github'),
    ],
];

