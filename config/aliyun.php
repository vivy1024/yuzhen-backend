<?php

/**
 * 阿里云服务配置
 * 
 * 包含阿里云号码认证服务(DYPNS)的配置项
 * SDK: alibabacloud/dypnsapi-20170525
 * 文档: https://help.aliyun.com/zh/pnvs/getting-started/sms-authentication-service-novice-guide
 * 
 * DYPNS优势：
 * - 无需企业资质
 * - 无需申请短信签名
 * - 无需申请短信模板
 * - 系统自动赠送签名和模板
 */

return [
    /*
    |--------------------------------------------------------------------------
    | 阿里云访问凭证
    |--------------------------------------------------------------------------
    |
    | 从阿里云控制台获取的AccessKey ID和AccessKey Secret
    | 建议使用RAM子账号，仅授予短信服务相关权限
    |
    */
    'access_key_id' => env('ALIYUN_ACCESS_KEY_ID', ''),
    'access_key_secret' => env('ALIYUN_ACCESS_KEY_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | 短信服务配置（DYPNS）
    |--------------------------------------------------------------------------
    |
    | region: 服务区域，默认cn-hangzhou
    | sign_name: 短信签名（系统预置，需在控制台查看）
    | template_code: 短信模板（系统预置，需在控制台查看）
    | code_expire: 验证码有效期（秒），默认5分钟
    | code_length: 验证码位数，默认6位
    | 
    | 注意：虽然DYPNS是免资质服务，但仍需配置签名和模板
    |      这些是系统预置的，不需要申请审核
    |      请登录阿里云控制台查看您的预置签名和模板代码
    |
    */
    'sms' => [
        'region' => env('ALIYUN_SMS_REGION', 'cn-hangzhou'),
        'sign_name' => env('ALIYUN_SMS_SIGN_NAME', ''),
        'template_code' => env('ALIYUN_SMS_TEMPLATE_CODE', ''),
        'code_expire' => (int) env('ALIYUN_SMS_CODE_EXPIRE', 300),
        'code_length' => 6,
    ],

    /*
    |--------------------------------------------------------------------------
    | 频率限制配置
    |--------------------------------------------------------------------------
    |
    | send_interval: 同一手机号发送间隔（秒）
    | daily_limit: 同一手机号每日发送上限
    | ip_minute_limit: 同一IP每分钟发送上限
    | verify_fail_limit: 验证失败次数上限
    | lock_duration: 锁定时长（秒）
    |
    */
    'rate_limit' => [
        'send_interval' => 60,
        'daily_limit' => 10,
        'ip_minute_limit' => 20,
        'verify_fail_limit' => 5,
        'lock_duration' => 900,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Key前缀配置
    |--------------------------------------------------------------------------
    |
    | 用于存储验证码和频率限制数据的Redis Key前缀
    |
    */
    'redis_keys' => [
        'code' => 'sms:code:',
        'send_time' => 'sms:send_time:',
        'daily_count' => 'sms:daily_count:',
        'ip_count' => 'sms:ip_count:',
        'fail_count' => 'sms:fail_count:',
        'locked' => 'sms:locked:',
    ],
];
