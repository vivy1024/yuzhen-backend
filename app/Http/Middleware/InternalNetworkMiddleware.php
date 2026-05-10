<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 内部网络访问限制中间件
 *
 * 仅允许来自内部网络（Docker 容器间通信、本地回环）的请求通过。
 * 用于保护 /metrics 等运维端点。
 *
 * SEC-3: 安全加固 2026-05
 */
class InternalNetworkMiddleware
{
    /**
     * 允许的 IP 范围（CIDR 格式）
     */
    private array $allowedRanges = [
        '127.0.0.1/32',     // IPv4 回环
        '::1/128',          // IPv6 回环
        '172.16.0.0/12',    // Docker 默认网络
        '10.0.0.0/8',       // 内部网络
        '192.168.0.0/16',   // 局域网
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $clientIp = $request->ip();

        if ($this->isAllowed($clientIp)) {
            return $next($request);
        }

        \Log::warning('Blocked access to internal endpoint', [
            'ip' => $clientIp,
            'path' => $request->path(),
        ]);

        return response('Forbidden', 403);
    }

    private function isAllowed(string $ip): bool
    {
        foreach ($this->allowedRanges as $range) {
            if ($this->ipInCidr($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        // 处理纯 IP（无 CIDR 后缀）
        if (!str_contains($cidr, '/')) {
            return $ip === $cidr;
        }

        [$subnet, $bits] = explode('/', $cidr);

        // IPv6 简单匹配
        if (str_contains($subnet, ':')) {
            return $ip === $subnet;
        }

        // IPv4 CIDR 匹配
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $mask = -1 << (32 - (int) $bits);

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
