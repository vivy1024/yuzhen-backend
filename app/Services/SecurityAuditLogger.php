<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * SecurityAuditLogger - 安全审计日志服务
 * 
 * 安全加固：Requirements 11.1, 11.2, 11.3, 11.4
 * 记录安全相关事件，用于追踪和分析安全事件
 * 
 * 功能：
 * - 记录认证失败事件
 * - 记录管理员操作事件
 * - 记录敏感配置变更事件
 * - 检测异常访问模式
 * 
 * @version v1.0.0
 * @date 2026-01-18
 * @author 薛小川
 * @requirements 11.1, 11.2, 11.3, 11.4
 */
class SecurityAuditLogger
{
    /**
     * 安全日志通道名称
     */
    private const CHANNEL = 'security';
    
    /**
     * 异常访问检测阈值：时间窗口内的最大请求数
     */
    private const ANOMALY_REQUEST_THRESHOLD = 100;
    
    /**
     * 异常访问检测阈值：时间窗口（秒）
     */
    private const ANOMALY_TIME_WINDOW = 300;
    
    /**
     * 记录认证失败事件
     * 
     * @param string $type 认证类型（如：login, token, internal_api）
     * @param string $identifier 标识符（如：用户名、邮箱、IP）
     * @param string $ip 请求IP地址
     * @param array $context 额外上下文信息
     * @return void
     * 
     * 示例：
     * SecurityAuditLogger::logAuthFailure('login', 'user@example.com', '192.168.1.1', [
     *     'reason' => 'invalid_password',
     *     'attempts' => 3
     * ]);
     * 
     * @requirements 11.1
     */
    public static function logAuthFailure(
        string $type,
        string $identifier,
        string $ip,
        array $context = []
    ): void {
        Log::channel(self::CHANNEL)->warning('Authentication failure', [
            'type' => $type,
            'identifier' => self::maskSensitiveIdentifier($identifier),
            'ip' => $ip,
            'timestamp' => now()->toIso8601String(),
            'context' => $context,
        ]);
    }
    
    /**
     * 记录管理员操作
     * 
     * @param int $adminId 管理员ID
     * @param string $action 操作类型（如：create, update, delete, view）
     * @param string $resource 操作资源（如：user, config, membership）
     * @param array $details 操作详情
     * @return void
     * 
     * 示例：
     * SecurityAuditLogger::logAdminAction(1, 'update', 'user', [
     *     'user_id' => 123,
     *     'changes' => ['role' => 'admin']
     * ]);
     * 
     * @requirements 11.2
     */
    public static function logAdminAction(
        int $adminId,
        string $action,
        string $resource,
        array $details = []
    ): void {
        Log::channel(self::CHANNEL)->info('Admin action', [
            'admin_id' => $adminId,
            'action' => $action,
            'resource' => $resource,
            'details' => self::filterSensitiveData($details),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
    
    /**
     * 记录配置变更
     * 
     * @param string $config 配置名称
     * @param string $changedBy 变更者标识
     * @param array $changes 变更内容
     * @return void
     * 
     * 示例：
     * SecurityAuditLogger::logConfigChange('cors', 'admin:1', [
     *     'allowed_origins' => ['old' => ['*'], 'new' => ['https://example.com']]
     * ]);
     * 
     * @requirements 11.3
     */
    public static function logConfigChange(
        string $config,
        string $changedBy,
        array $changes = []
    ): void {
        Log::channel(self::CHANNEL)->notice('Configuration change', [
            'config' => $config,
            'changed_by' => $changedBy,
            'changes' => self::filterSensitiveData($changes),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
    
    /**
     * 检测异常访问模式
     * 
     * 简单的速率检测：在指定时间窗口内超过阈值请求数则视为异常
     * 
     * @param string $ip IP地址
     * @param int $requestCount 请求次数
     * @param int $timeWindow 时间窗口（秒）
     * @return bool 是否检测到异常模式
     * 
     * 示例：
     * $isAnomalous = SecurityAuditLogger::detectAnomalousPattern('192.168.1.1', 150, 300);
     * // 5分钟内150次请求，返回 true（异常）
     * 
     * @requirements 11.4
     */
    public static function detectAnomalousPattern(
        string $ip,
        int $requestCount,
        int $timeWindow
    ): bool {
        // 简单的速率检测：时间窗口内超过阈值请求数
        if ($requestCount > self::ANOMALY_REQUEST_THRESHOLD && $timeWindow <= self::ANOMALY_TIME_WINDOW) {
            Log::channel(self::CHANNEL)->alert('Anomalous access pattern detected', [
                'ip' => $ip,
                'request_count' => $requestCount,
                'time_window' => $timeWindow,
                'threshold' => self::ANOMALY_REQUEST_THRESHOLD,
                'timestamp' => now()->toIso8601String(),
            ]);
            return true;
        }
        return false;
    }
    
    /**
     * 记录安全告警
     * 
     * @param string $alertType 告警类型
     * @param string $message 告警消息
     * @param array $context 上下文信息
     * @return void
     * 
     * 示例：
     * SecurityAuditLogger::logSecurityAlert('brute_force', '检测到暴力破解尝试', [
     *     'ip' => '192.168.1.1',
     *     'attempts' => 10
     * ]);
     */
    public static function logSecurityAlert(
        string $alertType,
        string $message,
        array $context = []
    ): void {
        Log::channel(self::CHANNEL)->alert($message, [
            'alert_type' => $alertType,
            'context' => self::filterSensitiveData($context),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
    
    /**
     * 记录Token验证事件
     * 
     * @param string $tokenType Token类型（如：jwt, internal_api, refresh）
     * @param bool $success 验证是否成功
     * @param string $ip 请求IP
     * @param array $context 上下文信息
     * @return void
     */
    public static function logTokenValidation(
        string $tokenType,
        bool $success,
        string $ip,
        array $context = []
    ): void {
        $level = $success ? 'debug' : 'warning';
        $message = $success ? 'Token validation successful' : 'Token validation failed';
        
        Log::channel(self::CHANNEL)->$level($message, [
            'token_type' => $tokenType,
            'success' => $success,
            'ip' => $ip,
            'context' => $context,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
    
    /**
     * 脱敏处理敏感标识符
     * 
     * @param string $identifier 原始标识符
     * @return string 脱敏后的标识符
     */
    private static function maskSensitiveIdentifier(string $identifier): string
    {
        // 邮箱脱敏：user@example.com -> u***@example.com
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $parts = explode('@', $identifier);
            $localPart = $parts[0];
            $domain = $parts[1];
            
            if (strlen($localPart) > 1) {
                $masked = $localPart[0] . str_repeat('*', min(3, strlen($localPart) - 1));
            } else {
                $masked = '*';
            }
            
            return $masked . '@' . $domain;
        }
        
        // 其他标识符：保留前2个和后2个字符
        $length = strlen($identifier);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }
        
        return substr($identifier, 0, 2) . str_repeat('*', $length - 4) . substr($identifier, -2);
    }
    
    /**
     * 过滤敏感数据
     * 
     * @param array $data 原始数据
     * @return array 过滤后的数据
     */
    private static function filterSensitiveData(array $data): array
    {
        $sensitiveKeys = [
            'password', 'secret', 'key', 'token', 
            'credential', 'auth', 'api_key', 'jwt',
            'private', 'credit_card', 'ssn'
        ];
        
        $filtered = [];
        foreach ($data as $key => $value) {
            $lowerKey = strtolower($key);
            
            // 检查是否为敏感键
            $isSensitive = false;
            foreach ($sensitiveKeys as $sensitiveKey) {
                if (str_contains($lowerKey, $sensitiveKey)) {
                    $isSensitive = true;
                    break;
                }
            }
            
            if ($isSensitive) {
                $filtered[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $filtered[$key] = self::filterSensitiveData($value);
            } else {
                $filtered[$key] = $value;
            }
        }
        
        return $filtered;
    }
}
