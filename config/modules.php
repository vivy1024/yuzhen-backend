<?php

/**
 * 模块配置
 * 
 * 管理所有微服务模块的配置
 */
return [
    
    /*
    |--------------------------------------------------------------------------
    | 启用的模块
    |--------------------------------------------------------------------------
    */
    'enabled' => [
        'exercise',
        'user',
        'auth',
        'training',
        'membership',
        'admin',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | 模块配置
    |--------------------------------------------------------------------------
    */
    'exercise' => [
        'enabled' => env('MODULE_EXERCISE_ENABLED', true),
        'namespace' => 'App\Modules\Exercise',
        'route_prefix' => 'exercises',
        'middleware' => ['api'],
        'cache_ttl' => 3600,
    ],
    
    'user' => [
        'enabled' => env('MODULE_USER_ENABLED', true),
        'namespace' => 'App\Modules\User',
        'route_prefix' => 'users',
        'middleware' => ['api', 'auth:sanctum'],
    ],
    
    'auth' => [
        'enabled' => env('MODULE_AUTH_ENABLED', true),
        'namespace' => 'App\Modules\Auth',
        'route_prefix' => 'auth',
        'middleware' => ['api'],
        'jwt_enabled' => true,
    ],
    
    'training' => [
        'enabled' => env('MODULE_TRAINING_ENABLED', true),
        'namespace' => 'App\Modules\Training',
        'route_prefix' => 'training',
        'middleware' => ['api', 'auth:sanctum'],
    ],
    
    'membership' => [
        'enabled' => env('MODULE_MEMBERSHIP_ENABLED', true),
        'namespace' => 'App\Modules\Membership',
        'route_prefix' => 'membership',
        'middleware' => ['api', 'auth:sanctum'],
    ],
    
    'admin' => [
        'enabled' => env('MODULE_ADMIN_ENABLED', true),
        'namespace' => 'App\Modules\Admin',
        'route_prefix' => 'admin',
        'middleware' => ['api', 'auth:sanctum', 'role:admin'],
    ],
    
];

