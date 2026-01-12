<?php

/**
 * 会员系统配置
 * 
 * @version v1.0.0
 * @date 2026-01-11
 * @author 薛小川
 */

return [

    /*
    |--------------------------------------------------------------------------
    | 会员系统开关（Feature Flag）
    |--------------------------------------------------------------------------
    |
    | 控制会员系统是否启用：
    | - false: 开发测试阶段，所有用户获得ENERGY级别权限，但仍受每日限制
    | - true: 商业化阶段，根据实际会员等级分配权限
    |
    */

    'enabled' => env('MEMBERSHIP_SYSTEM_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | 开发测试阶段限制
    |--------------------------------------------------------------------------
    |
    | 当会员系统禁用时（enabled=false），所有用户使用这些限制
    |
    */

    'dev_limits' => [
        'daily_dag_limit' => env('DEV_DAILY_DAG_LIMIT', 10),
        'daily_agent_limit' => env('DEV_DAILY_AGENT_LIMIT', 3),
        'can_use_agent' => true,
        'dag_templates' => 'all',
        'training_plan_limit' => 999999,
        'advanced_analysis' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | 会员等级配置
    |--------------------------------------------------------------------------
    |
    | 各会员等级的默认限制配置
    |
    */

    'tiers' => [
        'free' => [
            'name' => '免费用户',
            'daily_dag_limit' => 5,
            'daily_agent_limit' => 0,
            'can_use_agent' => false,
            'dag_templates' => ['greeting', 'simple_exercise_query'],
            'training_plan_limit' => 3,
            'advanced_analysis' => false,
        ],
        'warmheart' => [
            'name' => '暖心会员',
            'daily_dag_limit' => 10,
            'daily_agent_limit' => 0,
            'can_use_agent' => false,
            'dag_templates' => [
                'greeting', 'simple_exercise_query', 'exercise_detail',
                'muscle_exercise_query', 'equipment_exercise_query',
                'exercise_comparison', 'workout_plan_simple',
                'nutrition_query', 'food_search', 'meal_plan',
                'fitness_qa', 'progress_tracking', 'goal_setting'
            ],
            'training_plan_limit' => 10,
            'advanced_analysis' => false,
        ],
        'energy' => [
            'name' => '能量会员',
            'daily_dag_limit' => 999999,
            'daily_agent_limit' => 999999,
            'can_use_agent' => true,
            'dag_templates' => 'all',
            'training_plan_limit' => 999999,
            'advanced_analysis' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 缓存配置
    |--------------------------------------------------------------------------
    |
    | 权限缓存相关配置
    |
    */

    'cache' => [
        'ttl' => env('MEMBERSHIP_CACHE_TTL', 300), // 5分钟
        'prefix' => 'membership:',
    ],

];
