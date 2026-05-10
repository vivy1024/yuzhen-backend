<?php

namespace App\Modules\Auth\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * 图形验证码控制器
 * 
 * 生成 SVG 图形验证码，用于发送短信/邮件验证码前的人机验证
 */
class CaptchaController extends BaseController
{
    /**
     * 生成图形验证码
     * 
     * GET /api/auth/captcha
     * 返回: { captcha_id, svg, expires_in }
     */
    public function generate(): JsonResponse
    {
        $captchaId = Str::uuid()->toString();
        
        // 生成随机算术题
        $num1 = rand(1, 20);
        $num2 = rand(1, 20);
        $operators = ['+', '-', '×'];
        $opIndex = rand(0, 2);
        $operator = $operators[$opIndex];
        
        switch ($opIndex) {
            case 0: $answer = $num1 + $num2; break;
            case 1: 
                // 确保结果为正数
                if ($num1 < $num2) { [$num1, $num2] = [$num2, $num1]; }
                $answer = $num1 - $num2; 
                break;
            case 2: 
                $num1 = rand(2, 9);
                $num2 = rand(2, 9);
                $answer = $num1 * $num2; 
                break;
            default: $answer = $num1 + $num2;
        }
        
        // 存储答案到 Redis（2分钟有效）
        Cache::put("captcha:{$captchaId}", (string) $answer, 120);
        
        // 生成 SVG
        $expression = "{$num1} {$operator} {$num2} = ?";
        $svg = $this->generateSvg($expression);
        
        return $this->success([
            'captcha_id' => $captchaId,
            'svg' => $svg,
            'expires_in' => 120,
        ]);
    }

    /**
     * 验证图形验证码
     * 
     * POST /api/auth/captcha/verify
     * 参数: { captcha_id, answer }
     * 返回: { captcha_token } 或错误
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'captcha_id' => 'required|string',
            'answer' => 'required|string',
        ]);

        $captchaId = $request->input('captcha_id');
        $userAnswer = trim($request->input('answer'));
        
        $correctAnswer = Cache::get("captcha:{$captchaId}");
        
        if ($correctAnswer === null) {
            return $this->fail('验证码已过期，请重新获取', 410);
        }
        
        if ($userAnswer !== $correctAnswer) {
            return $this->fail('验证码错误', 422);
        }
        
        // 验证通过，删除已用验证码
        Cache::forget("captcha:{$captchaId}");
        
        // 生成一次性 token（5分钟有效）
        $captchaToken = Str::random(64);
        Cache::put("captcha_token:{$captchaToken}", true, 300);
        
        return $this->success([
            'captcha_token' => $captchaToken,
        ]);
    }

    /**
     * 验证 captcha_token 是否有效（供内部调用）
     */
    public static function validateToken(?string $token): bool
    {
        if (!$token) return false;
        $valid = Cache::has("captcha_token:{$token}");
        if ($valid) {
            Cache::forget("captcha_token:{$token}"); // 一次性使用
        }
        return $valid;
    }

    /**
     * 生成 SVG 图形验证码
     */
    private function generateSvg(string $text): string
    {
        $width = 200;
        $height = 60;
        
        // 随机背景色（浅色）
        $bgR = rand(230, 255);
        $bgG = rand(230, 255);
        $bgB = rand(230, 255);
        
        // 干扰线
        $lines = '';
        for ($i = 0; $i < 4; $i++) {
            $x1 = rand(0, $width);
            $y1 = rand(0, $height);
            $x2 = rand(0, $width);
            $y2 = rand(0, $height);
            $r = rand(100, 200);
            $g = rand(100, 200);
            $b = rand(100, 200);
            $lines .= "<line x1=\"{$x1}\" y1=\"{$y1}\" x2=\"{$x2}\" y2=\"{$y2}\" stroke=\"rgb({$r},{$g},{$b})\" stroke-width=\"1\" />";
        }
        
        // 干扰点
        $dots = '';
        for ($i = 0; $i < 30; $i++) {
            $cx = rand(0, $width);
            $cy = rand(0, $height);
            $r = rand(100, 200);
            $g = rand(100, 200);
            $b = rand(100, 200);
            $dots .= "<circle cx=\"{$cx}\" cy=\"{$cy}\" r=\"1\" fill=\"rgb({$r},{$g},{$b})\" />";
        }
        
        // 文字（带随机旋转和偏移）
        $fontSize = rand(24, 30);
        $textX = rand(30, 50);
        $textY = rand(35, 45);
        $rotation = rand(-5, 5);
        $textR = rand(20, 80);
        $textG = rand(20, 80);
        $textB = rand(20, 80);
        
        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$width}" height="{$height}" viewBox="0 0 {$width} {$height}">
  <rect width="100%" height="100%" fill="rgb({$bgR},{$bgG},{$bgB})" />
  {$lines}
  {$dots}
  <text x="{$textX}" y="{$textY}" font-size="{$fontSize}" font-family="Arial, sans-serif" font-weight="bold" fill="rgb({$textR},{$textG},{$textB})" transform="rotate({$rotation} {$textX} {$textY})">{$text}</text>
</svg>
SVG;
    }
}
