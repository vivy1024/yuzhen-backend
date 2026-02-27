<?php

namespace App\Modules\Auth\Services;

use App\Modules\User\Models\User;
use App\Modules\Auth\Events\UserLoggedIn;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Exception;

/**
 * 短信验证服务
 * 
 * 提供短信验证码的发送、验证、频率限制等功能
 * 使用阿里云DYPNS（号码认证服务）
 * 
 * @version 2.0.0
 */
class SmsService
{
    private AliyunDypnsClient $dypnsClient;
    private JwtService $jwtService;
    
    // 配置项
    private int $codeExpire;
    private int $codeLength;
    private int $sendInterval;
    private int $dailyLimit;
    private int $ipMinuteLimit;
    private int $verifyFailLimit;
    private int $lockDuration;
    
    // Redis Key前缀
    private array $redisKeys;

    public function __construct(AliyunDypnsClient $dypnsClient, JwtService $jwtService)
    {
        $this->dypnsClient = $dypnsClient;
        $this->jwtService = $jwtService;
        
        // 加载配置
        $this->codeExpire = config('aliyun.sms.code_expire', 300);
        $this->codeLength = config('aliyun.sms.code_length', 6);
        $this->sendInterval = config('aliyun.rate_limit.send_interval', 60);
        $this->dailyLimit = config('aliyun.rate_limit.daily_limit', 10);
        $this->ipMinuteLimit = config('aliyun.rate_limit.ip_minute_limit', 5);
        $this->verifyFailLimit = config('aliyun.rate_limit.verify_fail_limit', 5);
        $this->lockDuration = config('aliyun.rate_limit.lock_duration', 900);
        $this->redisKeys = config('aliyun.redis_keys', [
            'code' => 'sms:code:',
            'send_time' => 'sms:send_time:',
            'daily_count' => 'sms:daily_count:',
            'ip_count' => 'sms:ip_count:',
            'fail_count' => 'sms:fail_count:',
            'locked' => 'sms:locked:',
        ]);
    }

    /**
     * 发送验证码
     * 
     * @param string $phone 手机号
     * @param string $ip 请求IP
     * @return array ['success' => bool, 'message' => string, 'expires_at' => int, 'wait_seconds' => int]
     */
    public function sendVerificationCode(string $phone, string $ip): array
    {
        // 1. 验证手机号格式
        if (!$this->isValidPhone($phone)) {
            return [
                'success' => false,
                'message' => '手机号格式不正确',
                'expires_at' => 0,
                'wait_seconds' => 0,
            ];
        }

        // 2. 检查频率限制
        $rateCheck = $this->checkRateLimit($phone, $ip);
        if (!$rateCheck['allowed']) {
            return [
                'success' => false,
                'message' => $rateCheck['message'],
                'expires_at' => 0,
                'wait_seconds' => $rateCheck['wait_seconds'],
            ];
        }

        // 3. 生成验证码（免资质模板需要自行生成）
        $code = $this->generateCode();

        // 4. 发送短信
        $result = $this->dypnsClient->sendSmsVerifyCode($phone, $code);

        if (!$result['success']) {
            return [
                'success' => false,
                'message' => $result['message'],
                'expires_at' => 0,
                'wait_seconds' => 0,
            ];
        }

        // 5. 存储验证码到 Redis（免资质模板需本地存储）
        $this->storeCode($phone, $code);

        // 6. 更新发送记录
        $this->updateSendRecord($phone, $ip);

        $expiresAt = time() + $this->codeExpire;

        return [
            'success' => true,
            'message' => '验证码已发送',
            'expires_at' => $expiresAt,
            'wait_seconds' => $this->sendInterval,
            'biz_id' => $result['biz_id'] ?? '',
        ];
    }

    /**
     * 验证验证码（本地Redis校验）
     *
     * 免资质模板验证码由本地存储，不走DYPNS API
     *
     * @param string $phone 手机号
     * @param string $code 验证码
     * @return array ['success' => bool, 'message' => string]
     */
    public function verifyCode(string $phone, string $code): array
    {
        // 1. 检查是否被锁定
        if ($this->isLocked($phone)) {
            $remainingTime = $this->getLockRemainingTime($phone);
            return [
                'success' => false,
                'message' => "验证失败次数过多，请{$remainingTime}秒后重试",
            ];
        }

        // 2. 从Redis获取存储的验证码
        $storedCode = $this->getStoredCode($phone);

        if (!$storedCode) {
            return [
                'success' => false,
                'message' => '验证码已过期或不存在',
            ];
        }

        // 3. 验证验证码
        if ($code !== $storedCode) {
            // 验证失败，增加失败计数
            $this->incrementFailCount($phone);
            $failCount = $this->getFailCount($phone);
            $remaining = $this->verifyFailLimit - $failCount;

            if ($remaining <= 0) {
                $this->lockPhone($phone);
                return [
                    'success' => false,
                    'message' => '验证失败次数过多，请15分钟后重试',
                ];
            }

            return [
                'success' => false,
                'message' => "验证码错误，还剩{$remaining}次机会",
            ];
        }

        // 4. 验证成功，删除验证码并清除失败计数
        $this->deleteCode($phone);
        $this->clearFailCount($phone);

        return [
            'success' => true,
            'message' => '验证成功',
        ];
    }

    /**
     * 手机号验证码登录
     * 
     * @param string $phone 手机号
     * @param string $code 验证码
     * @param string $ip 请求IP
     * @return array
     */
    public function loginWithSms(string $phone, string $code, string $ip): array
    {
        // 1. 验证验证码
        $verifyResult = $this->verifyCode($phone, $code);
        if (!$verifyResult['success']) {
            return $verifyResult;
        }

        // 2. 查找用户
        $user = User::where('phone', $phone)->first();

        if (!$user) {
            return [
                'success' => false,
                'message' => '该手机号未注册，请先注册',
            ];
        }

        // 3. 检查用户状态
        if ($user->status !== 1) {
            return [
                'success' => false,
                'message' => '用户已被禁用',
            ];
        }

        // 4. 更新最后登录时间
        $user->updateLastLogin($ip);

        // 5. 生成Token
        $token = $this->jwtService->generateToken($user);
        $refreshToken = $this->jwtService->generateRefreshToken($user);

        // REQ-H5: 触发登录事件（与密码登录一致）
        Event::dispatch(new UserLoggedIn($user->toArray(), $ip));

        return [
            'success' => true,
            'message' => '登录成功',
            'data' => [
                'user' => $user->toArray(),
                'access_token' => $token,
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
                'expires_in' => config('auth.jwt_ttl', 3600),
            ],
        ];
    }

    /**
     * 生成6位数字验证码
     */
    private function generateCode(): string
    {
        $min = (int) str_pad('1', $this->codeLength, '0');
        $max = (int) str_pad('9', $this->codeLength, '9');
        return (string) random_int($min, $max);
    }

    /**
     * 验证手机号格式（中国大陆11位手机号）
     */
    private function isValidPhone(string $phone): bool
    {
        return preg_match('/^1[3-9]\d{9}$/', $phone) === 1;
    }

    /**
     * 检查发送频率限制
     */
    private function checkRateLimit(string $phone, string $ip): array
    {
        // 1. 检查手机号是否被锁定
        if ($this->isLocked($phone)) {
            $remainingTime = $this->getLockRemainingTime($phone);
            return [
                'allowed' => false,
                'wait_seconds' => $remainingTime,
                'message' => "手机号已被锁定，请{$remainingTime}秒后重试",
            ];
        }

        // 2. 检查发送间隔（60秒）
        $lastSendTime = Redis::get($this->redisKeys['send_time'] . $phone);
        if ($lastSendTime) {
            $elapsed = time() - (int) $lastSendTime;
            if ($elapsed < $this->sendInterval) {
                $waitSeconds = $this->sendInterval - $elapsed;
                return [
                    'allowed' => false,
                    'wait_seconds' => $waitSeconds,
                    'message' => "请{$waitSeconds}秒后再试",
                ];
            }
        }

        // 3. 检查每日发送限制
        $today = date('Y-m-d');
        $dailyCount = (int) Redis::get($this->redisKeys['daily_count'] . $phone . ':' . $today);
        if ($dailyCount >= $this->dailyLimit) {
            return [
                'allowed' => false,
                'wait_seconds' => 0,
                'message' => '今日发送次数已达上限',
            ];
        }

        // 4. 检查IP每分钟限制
        $minute = date('Y-m-d-H-i');
        $ipCount = (int) Redis::get($this->redisKeys['ip_count'] . $ip . ':' . $minute);
        if ($ipCount >= $this->ipMinuteLimit) {
            return [
                'allowed' => false,
                'wait_seconds' => 60,
                'message' => '请求过于频繁，请稍后再试',
            ];
        }

        return [
            'allowed' => true,
            'wait_seconds' => 0,
            'message' => '',
        ];
    }

    /**
     * 存储验证码到Redis
     */
    private function storeCode(string $phone, string $code): void
    {
        Redis::setex($this->redisKeys['code'] . $phone, $this->codeExpire, $code);
    }

    /**
     * 获取存储的验证码
     */
    private function getStoredCode(string $phone): ?string
    {
        $code = Redis::get($this->redisKeys['code'] . $phone);
        return $code ?: null;
    }

    /**
     * 删除验证码
     */
    private function deleteCode(string $phone): void
    {
        Redis::del($this->redisKeys['code'] . $phone);
    }

    /**
     * 更新发送记录
     */
    private function updateSendRecord(string $phone, string $ip): void
    {
        // 记录发送时间
        Redis::setex($this->redisKeys['send_time'] . $phone, $this->sendInterval, time());

        // 增加每日发送计数
        $today = date('Y-m-d');
        $dailyKey = $this->redisKeys['daily_count'] . $phone . ':' . $today;
        Redis::incr($dailyKey);
        Redis::expire($dailyKey, 86400);

        // 增加IP每分钟计数
        $minute = date('Y-m-d-H-i');
        $ipKey = $this->redisKeys['ip_count'] . $ip . ':' . $minute;
        Redis::incr($ipKey);
        Redis::expire($ipKey, 60);
    }

    /**
     * 检查手机号是否被锁定
     */
    private function isLocked(string $phone): bool
    {
        return Redis::exists($this->redisKeys['locked'] . $phone) > 0;
    }

    /**
     * 获取锁定剩余时间
     */
    private function getLockRemainingTime(string $phone): int
    {
        $ttl = Redis::ttl($this->redisKeys['locked'] . $phone);
        return max(0, $ttl);
    }

    /**
     * 锁定手机号
     */
    private function lockPhone(string $phone): void
    {
        Redis::setex($this->redisKeys['locked'] . $phone, $this->lockDuration, 1);
        $this->clearFailCount($phone);
        
        Log::channel('daily')->warning('手机号被锁定', [
            'phone' => $this->maskPhone($phone),
            'duration' => $this->lockDuration,
        ]);
    }

    /**
     * 增加验证失败计数
     */
    private function incrementFailCount(string $phone): void
    {
        $key = $this->redisKeys['fail_count'] . $phone;
        Redis::incr($key);
        Redis::expire($key, $this->lockDuration);
    }

    /**
     * 获取验证失败次数
     */
    private function getFailCount(string $phone): int
    {
        return (int) Redis::get($this->redisKeys['fail_count'] . $phone);
    }

    /**
     * 清除验证失败计数
     */
    private function clearFailCount(string $phone): void
    {
        Redis::del($this->redisKeys['fail_count'] . $phone);
    }

    /**
     * 手机号脱敏
     */
    private function maskPhone(string $phone): string
    {
        if (strlen($phone) >= 11) {
            return substr($phone, 0, 3) . '****' . substr($phone, -4);
        }
        return '***';
    }

    /**
     * 检查手机号是否已注册
     */
    public function isPhoneRegistered(string $phone): bool
    {
        return User::where('phone', $phone)->exists();
    }

    /**
     * 获取验证码剩余有效时间
     */
    public function getCodeTTL(string $phone): int
    {
        $ttl = Redis::ttl($this->redisKeys['code'] . $phone);
        return max(0, $ttl);
    }
}
