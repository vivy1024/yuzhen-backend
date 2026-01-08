<?php

namespace App\Modules\Auth\Services;

use App\Mail\VerificationCodeMail;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Exception;

/**
 * 邮箱验证服务
 * 
 * 提供邮箱验证码的发送、验证、频率限制等功能
 * 使用Laravel内置Mail功能，支持腾讯企业邮箱和Gmail
 * 
 * v1.1.0 - 改用Laravel Cache替代直接Redis调用
 * - 支持多种缓存驱动（file/redis/database）
 * - 生产环境更稳定，避免Redis连接问题
 * 
 * @version 1.1.0
 */
class EmailService
{
    private JwtService $jwtService;
    
    // 配置项
    private int $codeExpire;
    private int $codeLength;
    private int $sendInterval;
    private int $dailyLimit;
    private int $ipMinuteLimit;
    private int $verifyFailLimit;
    private int $lockDuration;
    
    // Cache Key前缀
    private array $cacheKeys;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
        
        // 加载配置
        $this->codeExpire = config('mail.verification_code_expire', 300);
        $this->codeLength = 6;
        $this->sendInterval = 60;
        $this->dailyLimit = 10;
        $this->ipMinuteLimit = 5;
        $this->verifyFailLimit = 5;
        $this->lockDuration = 900;
        
        $this->cacheKeys = [
            'code' => 'email:code:',
            'send_time' => 'email:send_time:',
            'daily_count' => 'email:daily_count:',
            'ip_count' => 'email:ip_count:',
            'fail_count' => 'email:fail_count:',
            'locked' => 'email:locked:',
        ];
    }

    /**
     * 发送验证码
     * 
     * @param string $email 邮箱地址
     * @param string $ip 请求IP
     * @return array ['success' => bool, 'message' => string, 'expires_at' => int, 'wait_seconds' => int]
     */
    public function sendVerificationCode(string $email, string $ip): array
    {
        // 1. 验证邮箱格式
        if (!$this->isValidEmail($email)) {
            return [
                'success' => false,
                'message' => '邮箱格式不正确',
                'expires_at' => 0,
                'wait_seconds' => 0,
            ];
        }

        // 2. 检查频率限制
        $rateCheck = $this->checkRateLimit($email, $ip);
        if (!$rateCheck['allowed']) {
            return [
                'success' => false,
                'message' => $rateCheck['message'],
                'expires_at' => 0,
                'wait_seconds' => $rateCheck['wait_seconds'],
            ];
        }

        // 3. 生成验证码
        $code = $this->generateCode();

        // 4. 发送邮件
        try {
            Mail::to($email)->send(new VerificationCodeMail($code));
            
            // 5. 存储验证码到缓存
            $codeKey = $this->cacheKeys['code'] . $email;
            Cache::put($codeKey, $code, $this->codeExpire);
            
            // 6. 记录发送时间
            $sendTimeKey = $this->cacheKeys['send_time'] . $email;
            Cache::put($sendTimeKey, time(), $this->sendInterval);
            
            // 7. 增加每日发送计数
            $dailyCountKey = $this->cacheKeys['daily_count'] . $email . ':' . date('Y-m-d');
            $dailyCount = Cache::get($dailyCountKey, 0);
            Cache::put($dailyCountKey, $dailyCount + 1, 86400);
            
            // 8. 增加IP每分钟计数
            $ipCountKey = $this->cacheKeys['ip_count'] . $ip . ':' . date('YmdHi');
            $ipCount = Cache::get($ipCountKey, 0);
            Cache::put($ipCountKey, $ipCount + 1, 60);
            
            // 9. 记录日志（邮箱脱敏）
            $maskedEmail = $this->maskEmail($email);
            Log::info('邮箱验证码发送成功', [
                'email' => $maskedEmail,
                'ip' => $ip,
                'expires_at' => time() + $this->codeExpire,
            ]);
            
            return [
                'success' => true,
                'message' => '验证码已发送到您的邮箱',
                'expires_at' => time() + $this->codeExpire,
                'wait_seconds' => 0,
            ];
            
        } catch (Exception $e) {
            Log::error('邮箱验证码发送失败', [
                'email' => $this->maskEmail($email),
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => '邮件发送失败，请稍后重试',
                'expires_at' => 0,
                'wait_seconds' => 0,
            ];
        }
    }

    /**
     * 验证验证码
     * 
     * @param string $email 邮箱地址
     * @param string $code 验证码
     * @return array ['success' => bool, 'message' => string]
     */
    public function verifyCode(string $email, string $code): array
    {
        // 1. 检查是否被锁定
        $lockedKey = $this->cacheKeys['locked'] . $email;
        if (Cache::has($lockedKey)) {
            $ttl = $this->getRemainingTtl($lockedKey);
            return [
                'success' => false,
                'message' => "验证失败次数过多，请{$ttl}秒后重试",
            ];
        }

        // 2. 获取存储的验证码
        $codeKey = $this->cacheKeys['code'] . $email;
        $storedCode = Cache::get($codeKey);

        if (!$storedCode) {
            return [
                'success' => false,
                'message' => '验证码已过期或不存在',
            ];
        }

        // 3. 验证验证码
        if ($code !== $storedCode) {
            // 增加失败计数
            $failCountKey = $this->cacheKeys['fail_count'] . $email;
            $failCount = Cache::get($failCountKey, 0) + 1;
            Cache::put($failCountKey, $failCount, $this->lockDuration);

            // 检查是否需要锁定
            if ($failCount >= $this->verifyFailLimit) {
                Cache::put($lockedKey, '1', $this->lockDuration);
                Log::warning('邮箱验证失败次数过多，已锁定', [
                    'email' => $this->maskEmail($email),
                    'fail_count' => $failCount,
                ]);
                return [
                    'success' => false,
                    'message' => '验证失败次数过多，已锁定15分钟',
                ];
            }

            Log::info('邮箱验证码验证失败', [
                'email' => $this->maskEmail($email),
                'fail_count' => $failCount,
            ]);

            return [
                'success' => false,
                'message' => '验证码错误',
            ];
        }

        // 4. 验证成功，删除验证码
        Cache::forget($codeKey);
        Cache::forget($this->cacheKeys['fail_count'] . $email);

        Log::info('邮箱验证码验证成功', [
            'email' => $this->maskEmail($email),
        ]);

        return [
            'success' => true,
            'message' => '验证成功',
        ];
    }
    
    /**
     * 获取缓存键的剩余TTL（秒）
     * 
     * @param string $key 缓存键
     * @return int 剩余秒数
     */
    private function getRemainingTtl(string $key): int
    {
        // Laravel Cache不直接支持TTL查询，使用默认值
        // 实际TTL由缓存驱动管理
        return $this->lockDuration;
    }

    /**
     * 邮箱验证码登录
     * 
     * @param string $email 邮箱地址
     * @param string $code 验证码
     * @return array ['success' => bool, 'message' => string, 'user' => User|null, 'tokens' => array|null]
     */
    public function login(string $email, string $code): array
    {
        // 1. 验证验证码
        $verifyResult = $this->verifyCode($email, $code);
        if (!$verifyResult['success']) {
            return [
                'success' => false,
                'message' => $verifyResult['message'],
                'user' => null,
                'tokens' => null,
            ];
        }

        // 2. 查找用户
        $user = User::where('email', $email)->first();
        if (!$user) {
            return [
                'success' => false,
                'message' => '该邮箱未注册',
                'user' => null,
                'tokens' => null,
            ];
        }

        // 3. 生成JWT Token
        $tokens = $this->jwtService->generateTokens($user);

        Log::info('邮箱验证码登录成功', [
            'user_id' => $user->id,
            'email' => $this->maskEmail($email),
        ]);

        return [
            'success' => true,
            'message' => '登录成功',
            'user' => $user,
            'tokens' => $tokens,
        ];
    }

    /**
     * 生成验证码
     * 
     * @return string
     */
    private function generateCode(): string
    {
        return str_pad((string)random_int(0, 999999), $this->codeLength, '0', STR_PAD_LEFT);
    }

    /**
     * 检查频率限制
     * 
     * @param string $email 邮箱地址
     * @param string $ip IP地址
     * @return array ['allowed' => bool, 'message' => string, 'wait_seconds' => int]
     */
    private function checkRateLimit(string $email, string $ip): array
    {
        // 1. 检查是否被锁定
        $lockedKey = $this->cacheKeys['locked'] . $email;
        if (Cache::has($lockedKey)) {
            $ttl = $this->getRemainingTtl($lockedKey);
            return [
                'allowed' => false,
                'message' => "该邮箱已被锁定，请{$ttl}秒后重试",
                'wait_seconds' => $ttl,
            ];
        }

        // 2. 检查发送间隔（60秒）
        $sendTimeKey = $this->cacheKeys['send_time'] . $email;
        if (Cache::has($sendTimeKey)) {
            return [
                'allowed' => false,
                'message' => "发送过于频繁，请{$this->sendInterval}秒后重试",
                'wait_seconds' => $this->sendInterval,
            ];
        }

        // 3. 检查每日发送次数（10次）
        $dailyCountKey = $this->cacheKeys['daily_count'] . $email . ':' . date('Y-m-d');
        $dailyCount = (int)Cache::get($dailyCountKey, 0);
        if ($dailyCount >= $this->dailyLimit) {
            return [
                'allowed' => false,
                'message' => '今日发送次数已达上限',
                'wait_seconds' => 0,
            ];
        }

        // 4. 检查IP每分钟发送次数（5次）
        $ipCountKey = $this->cacheKeys['ip_count'] . $ip . ':' . date('YmdHi');
        $ipCount = (int)Cache::get($ipCountKey, 0);
        if ($ipCount >= $this->ipMinuteLimit) {
            return [
                'allowed' => false,
                'message' => 'IP发送过于频繁，请稍后重试',
                'wait_seconds' => 60,
            ];
        }

        return [
            'allowed' => true,
            'message' => '',
            'wait_seconds' => 0,
        ];
    }

    /**
     * 验证邮箱格式
     * 
     * @param string $email 邮箱地址
     * @return bool
     */
    private function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * 邮箱脱敏
     * 
     * @param string $email 邮箱地址
     * @return string
     */
    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email;
        }
        
        $username = $parts[0];
        $domain = $parts[1];
        
        $len = strlen($username);
        if ($len <= 2) {
            $masked = $username[0] . '***';
        } else {
            $masked = $username[0] . str_repeat('*', min($len - 2, 4)) . $username[$len - 1];
        }
        
        return $masked . '@' . $domain;
    }

    /**
     * 重置密码
     * 
     * @param string $email 邮箱地址
     * @param string $code 验证码
     * @param string $newPassword 新密码
     * @return array ['success' => bool, 'message' => string]
     */
    public function resetPassword(string $email, string $code, string $newPassword): array
    {
        // 1. 验证验证码
        $verifyResult = $this->verifyCode($email, $code);
        if (!$verifyResult['success']) {
            return [
                'success' => false,
                'message' => $verifyResult['message'],
            ];
        }

        // 2. 查找用户
        $user = User::where('email', $email)->first();
        if (!$user) {
            return [
                'success' => false,
                'message' => '该邮箱未注册',
            ];
        }

        // 3. 更新密码
        try {
            $user->password = bcrypt($newPassword);
            $user->save();

            Log::info('密码重置成功', [
                'user_id' => $user->id,
                'email' => $this->maskEmail($email),
            ]);

            return [
                'success' => true,
                'message' => '密码重置成功',
            ];
        } catch (Exception $e) {
            Log::error('密码重置失败', [
                'email' => $this->maskEmail($email),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => '密码重置失败，请稍后重试',
            ];
        }
    }

    /**
     * 检查邮箱是否已注册
     * 
     * @param string $email 邮箱地址
     * @return array ['exists' => bool, 'message' => string]
     */
    public function checkEmailExists(string $email): array
    {
        if (!$this->isValidEmail($email)) {
            return [
                'exists' => false,
                'message' => '邮箱格式不正确',
            ];
        }

        $user = User::where('email', $email)->first();
        
        return [
            'exists' => $user !== null,
            'message' => $user ? '邮箱已注册' : '邮箱未注册',
        ];
    }
}
