<?php

/**
 * 会员系统配置
 * 
 * 用于控制会员系统的开关和默认限制
 * 
 * @version v1.0.0
 * @date 2026-01-09
 */

return [
    /*
    |--------------------------------------------------------------------------
    | 会员系统开关
    |--------------------------------------------------------------------------
    |
    | 控制会员系统是否启用
    | - true: 启用会员系统，区分免费版/暖心会员/能量会员
    | - false: 禁用会员系统，所有用户使用统一限制
    |
    | 禁用原因：个人开发者没有企业资质，个人ICP备案不能涉及经营性业务
    | 启用条件：获得个人独资企业资质后设置为 true
    |
    */
    'enabled' => env('MEMBERSHIP_SYSTEM_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | 统一用户限制（会员系统禁用时使用）
    |--------------------------------------------------------------------------
    |
    | 当会员系统禁用时，所有用户使用这些统一限制
    | 相当于"增强版免费用户"
    |
    */
    'unified_limits' => [
        // AI对话限制
        'ai_queries_per_day' => env('UNIFIED_AI_QUERIES_PER_DAY', 10),
        
        // 训练计划限制
        'max_training_plans' => env('UNIFIED_MAX_TRAINING_PLANS', 5),
        
        // DAG模板（全部开放）
        'dag_templates' => [
            'greeting', 'quick_consultation', 'exercise_optimization',
            'progress_analysis', 'safety_assessment', 'complete_training_plan',
            'nutrition_planning', 'comprehensive_fitness', 'rehabilitation_training',
            'posture_correction', 'plan_adjustment', 'fat_loss_program', 'strength_program'
        ],
        'dag_template_count' => 13,
        
        // 按复杂度分级限制（统一版本不区分）
        'complexity_limits' => [
            'simple' => 10,   // 简单场景
            'medium' => 5,    // 中等场景
            'complex' => 3    // 复杂场景
        ],
        
        // 功能权限
        'unlock_all_exercises' => true,
        'ai_recommendation' => true,
        'data_analysis' => false,
        'coach_service' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | 打赏奖励配置
    |--------------------------------------------------------------------------
    |
    | 用户打赏后可以获得额外的AI对话次数
    | 需要手动在后台为用户添加
    |
    */
    'donation_rewards' => [
        // 打赏金额 => 额外AI对话次数
        '5' => 50,    // 打赏5元，额外50次
        '10' => 120,  // 打赏10元，额外120次
        '20' => 300,  // 打赏20元，额外300次
        '50' => 1000, // 打赏50元，额外1000次
    ],
];
