<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * 记录已废弃路由的调用日志
 */
class DeprecatedRouteLogger
{
    public function handle(Request $request, Closure $next, string $replacement = ''): Response
    {
        Log::warning('Deprecated route called, use ' . ($replacement ?: 'new route') . ' instead', [
            'uri' => $request->getRequestUri(),
            'method' => $request->method(),
            'user_id' => $request->user()?->id,
        ]);

        return $next($request);
    }
}
