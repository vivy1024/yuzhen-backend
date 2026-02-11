<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 安全响应头中间件
 * 
 * 添加安全相关的HTTP响应头，防止常见的Web攻击：
 * - X-Content-Type-Options: 防止MIME类型嗅探
 * - X-Frame-Options: 防止点击劫持
 * - X-XSS-Protection: XSS保护
 * - Strict-Transport-Security: HSTS（仅生产环境）
 * - Content-Security-Policy: 内容安全策略
 * - Referrer-Policy: 引用策略
 * - Permissions-Policy: 权限策略
 * 
 * @see Requirements 10.1, 10.2, 10.3, 10.4, 10.5
 */
class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        
        // 防止MIME类型嗅探
        // Requirement 10.1: 配置X-Content-Type-Options: nosniff响应头
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        
        // 防止点击劫持
        // Requirement 10.2: 配置X-Frame-Options: DENY响应头
        $response->headers->set('X-Frame-Options', 'DENY');
        
        // XSS保护
        // Requirement 10.3: 配置X-XSS-Protection: 1; mode=block响应头
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        
        // 仅生产环境启用HSTS
        // Requirement 10.4: 配置Strict-Transport-Security响应头（仅生产环境）
        if (config('app.env') === 'production') {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }
        
        // 内容安全策略
        // Requirement 10.5: 配置Content-Security-Policy响应头
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; " .
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
            "style-src 'self' 'unsafe-inline'; " .
            "img-src 'self' data: https:; " .
            "font-src 'self' data:; " .
            "connect-src 'self' https://api.yuzhen-fitness.cn https://ai.yuzhen-fitness.cn"
        );
        
        // 引用策略
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        
        // 权限策略
        $response->headers->set(
            'Permissions-Policy',
            'geolocation=(), microphone=(), camera=()'
        );
        
        return $response;
    }
}
