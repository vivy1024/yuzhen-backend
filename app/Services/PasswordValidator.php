<?php

namespace App\Services;

/**
 * PasswordValidator - 密码强度验证服务
 * 
 * 安全加固：Requirements 7.1
 * 验证密码强度，确保符合安全要求
 * 
 * 密码要求：
 * - 至少16字符长度
 * - 包含大写字母
 * - 包含小写字母
 * - 包含数字
 * - 包含特殊字符
 * 
 * @version v1.0.0
 * @date 2026-01-18
 * @author 薛小川
 * @requirements 7.1
 */
class PasswordValidator
{
    /**
     * 最小密码长度
     */
    public const MIN_LENGTH = 16;
    
    /**
     * 特殊字符列表
     */
    public const SPECIAL_CHARS = '!@#$%^&*()_+-=[]{}|;:,.<>?';
    
    /**
     * 验证密码强度
     * 
     * @param string $password 待验证的密码
     * @return array{valid: bool, errors: array<string>}
     * 
     * 返回格式：
     * [
     *     'valid' => bool,      // 是否通过验证
     *     'errors' => array,    // 错误信息列表
     * ]
     * 
     * 示例：
     * $result = PasswordValidator::validate('weak');
     * // ['valid' => false, 'errors' => ['密码长度至少16字符', ...]]
     * 
     * $result = PasswordValidator::validate('StrongP@ssw0rd123!');
     * // ['valid' => true, 'errors' => []]
     */
    public static function validate(string $password): array
    {
        $errors = [];
        
        // 检查长度
        if (strlen($password) < self::MIN_LENGTH) {
            $errors[] = '密码长度至少' . self::MIN_LENGTH . '字符';
        }
        
        // 检查大写字母
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = '密码必须包含大写字母';
        }
        
        // 检查小写字母
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = '密码必须包含小写字母';
        }
        
        // 检查数字
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = '密码必须包含数字';
        }
        
        // 检查特殊字符
        $specialCharsPattern = '/[' . preg_quote(self::SPECIAL_CHARS, '/') . ']/';
        if (!preg_match($specialCharsPattern, $password)) {
            $errors[] = '密码必须包含特殊字符';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * 检查密码是否有效
     * 
     * 简化版验证方法，仅返回布尔值
     * 
     * @param string $password 待验证的密码
     * @return bool 密码是否符合强度要求
     * 
     * 示例：
     * PasswordValidator::isValid('weak'); // false
     * PasswordValidator::isValid('StrongP@ssw0rd123!'); // true
     */
    public static function isValid(string $password): bool
    {
        return self::validate($password)['valid'];
    }
    
    /**
     * 获取密码强度要求说明
     * 
     * @return array<string> 密码要求列表
     */
    public static function getRequirements(): array
    {
        return [
            '密码长度至少' . self::MIN_LENGTH . '字符',
            '必须包含大写字母 (A-Z)',
            '必须包含小写字母 (a-z)',
            '必须包含数字 (0-9)',
            '必须包含特殊字符 (' . self::SPECIAL_CHARS . ')',
        ];
    }
    
    /**
     * 计算密码强度分数
     * 
     * @param string $password 待评估的密码
     * @return array{score: int, level: string, details: array}
     * 
     * 返回格式：
     * [
     *     'score' => int,       // 0-100 分数
     *     'level' => string,    // 强度等级：weak/medium/strong/very_strong
     *     'details' => array,   // 各项检查详情
     * ]
     */
    public static function getStrengthScore(string $password): array
    {
        $score = 0;
        $details = [];
        
        // 长度评分（最高40分）
        $length = strlen($password);
        if ($length >= self::MIN_LENGTH) {
            $lengthScore = min(40, 20 + ($length - self::MIN_LENGTH) * 2);
            $score += $lengthScore;
            $details['length'] = ['passed' => true, 'score' => $lengthScore];
        } else {
            $lengthScore = (int)(($length / self::MIN_LENGTH) * 20);
            $score += $lengthScore;
            $details['length'] = ['passed' => false, 'score' => $lengthScore];
        }
        
        // 大写字母（15分）
        if (preg_match('/[A-Z]/', $password)) {
            $score += 15;
            $details['uppercase'] = ['passed' => true, 'score' => 15];
        } else {
            $details['uppercase'] = ['passed' => false, 'score' => 0];
        }
        
        // 小写字母（15分）
        if (preg_match('/[a-z]/', $password)) {
            $score += 15;
            $details['lowercase'] = ['passed' => true, 'score' => 15];
        } else {
            $details['lowercase'] = ['passed' => false, 'score' => 0];
        }
        
        // 数字（15分）
        if (preg_match('/[0-9]/', $password)) {
            $score += 15;
            $details['numbers'] = ['passed' => true, 'score' => 15];
        } else {
            $details['numbers'] = ['passed' => false, 'score' => 0];
        }
        
        // 特殊字符（15分）
        $specialCharsPattern = '/[' . preg_quote(self::SPECIAL_CHARS, '/') . ']/';
        if (preg_match($specialCharsPattern, $password)) {
            $score += 15;
            $details['special'] = ['passed' => true, 'score' => 15];
        } else {
            $details['special'] = ['passed' => false, 'score' => 0];
        }
        
        // 确定强度等级
        $level = match (true) {
            $score >= 90 => 'very_strong',
            $score >= 70 => 'strong',
            $score >= 50 => 'medium',
            default => 'weak',
        };
        
        return [
            'score' => min(100, $score),
            'level' => $level,
            'details' => $details,
        ];
    }
}
