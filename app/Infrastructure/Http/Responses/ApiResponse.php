<?php

namespace App\Infrastructure\Http\Responses;

use Illuminate\Http\JsonResponse;

/**
 * 统一API响应格式
 * 
 * 响应结构:
 * {
 *   "code": 200,
 *   "msg": "操作成功",
 *   "data": {...}
 * }
 * 
 * @version 2.0.0 - Infrastructure Layer
 * @date 2025-11-01
 */
class ApiResponse
{
    /**
     * 状态码常量
     */
    const SUCCESS = 200;
    const FAIL = 500;
    const WARN = 601;
    
    // 客户端错误 (4xx)
    const BAD_REQUEST = 400;
    const UNAUTHORIZED = 401;
    const FORBIDDEN = 403;
    const NOT_FOUND = 404;
    const UNPROCESSABLE_ENTITY = 422;
    const TOO_MANY_REQUESTS = 429;
    
    // 服务器错误 (5xx)
    const INTERNAL_SERVER_ERROR = 500;
    const SERVICE_UNAVAILABLE = 503;

    /**
     * 成功响应
     */
    public static function ok($data = null, string $msg = '操作成功', int $httpCode = 200): JsonResponse
    {
        return response()->json([
            'code' => self::SUCCESS,
            'msg' => $msg,
            'data' => $data
        ], $httpCode);
    }

    /**
     * 失败响应
     */
    public static function fail(string $msg = '操作失败', int $code = self::FAIL, $data = null, int $httpCode = null): JsonResponse
    {
        if ($httpCode === null) {
            $httpCode = $code >= 500 ? 500 : $code;
        }
        
        return response()->json([
            'code' => $code,
            'msg' => $msg,
            'data' => $data
        ], $httpCode);
    }

    /**
     * 警告响应
     */
    public static function warn(string $msg, $data = null): JsonResponse
    {
        return response()->json([
            'code' => self::WARN,
            'msg' => $msg,
            'data' => $data
        ], 200);
    }

    /**
     * 错误响应
     */
    public static function error(string $msg, int $code = self::INTERNAL_SERVER_ERROR): JsonResponse
    {
        return self::fail($msg, $code, null, 500);
    }

    /**
     * 400 Bad Request
     */
    public static function badRequest(string $msg = '请求参数错误'): JsonResponse
    {
        return self::fail($msg, self::BAD_REQUEST, null, 400);
    }

    /**
     * 401 Unauthorized
     */
    public static function unauthorized(string $msg = '未授权，请先登录'): JsonResponse
    {
        return self::fail($msg, self::UNAUTHORIZED, null, 401);
    }

    /**
     * 403 Forbidden
     */
    public static function forbidden(string $msg = '没有权限访问该资源'): JsonResponse
    {
        return self::fail($msg, self::FORBIDDEN, null, 403);
    }

    /**
     * 404 Not Found
     */
    public static function notFound(string $msg = '资源不存在'): JsonResponse
    {
        return self::fail($msg, self::NOT_FOUND, null, 404);
    }

    /**
     * 422 Validation Error
     */
    public static function validationError(string $msg = '数据验证失败', $errors = null): JsonResponse
    {
        return self::fail($msg, self::UNPROCESSABLE_ENTITY, ['errors' => $errors], 422);
    }

    /**
     * 429 Too Many Requests
     */
    public static function tooManyRequests(string $msg = '请求过于频繁，请稍后再试'): JsonResponse
    {
        return self::fail($msg, self::TOO_MANY_REQUESTS, null, 429);
    }

    /**
     * 503 Service Unavailable
     */
    public static function serviceUnavailable(string $msg = '服务暂时不可用'): JsonResponse
    {
        return self::fail($msg, self::SERVICE_UNAVAILABLE, null, 503);
    }

    /**
     * 分页响应（支持数组或pagination对象）
     */
    public static function page($data, string $msg = '查询成功'): JsonResponse
    {
        // 如果data已经是结构化的分页数据
        if (is_array($data) && isset($data['rows'])) {
            return self::ok($data, $msg);
        }
        
        // 否则包装成统一格式
        return self::ok([
            'rows' => is_array($data) ? $data : [$data],
            'total' => is_array($data) ? count($data) : 1,
            'page' => 1,
            'per_page' => is_array($data) ? count($data) : 1,
            'total_pages' => 1,
        ], $msg);
    }
}

