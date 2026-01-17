<?php

namespace App\Infrastructure\Http\Controllers;

use App\Infrastructure\Http\Responses\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

/**
 * 基础控制器类
 * 
 * 提供统一的API响应格式和错误处理机制
 * 
 * @version 2.0.0 - Infrastructure Layer
 * @date 2025-11-01
 */
abstract class BaseController extends Controller
{
    use AuthorizesRequests, ValidatesRequests;
    
    /**
     * 统一成功响应格式
     */
    protected function success($data = null, string $msg = '操作成功', int $httpCode = 200): JsonResponse
    {
        return ApiResponse::ok($data, $msg, $httpCode);
    }
    
    /**
     * 统一失败响应格式
     */
    protected function fail(string $msg = '操作失败', int $code = ApiResponse::FAIL, $data = null, int $httpCode = null): JsonResponse
    {
        if ($code >= 500) {
            Log::error('API Error Response', [
                'message' => $msg,
                'code' => $code,
                'data' => $data,
                'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)
            ]);
        }
        
        return ApiResponse::fail($msg, $code, $data, $httpCode);
    }
    
    /**
     * 统一分页响应格式
     */
    protected function page($rows, int $total, int $page = 1, int $perPage = 20, string $msg = '查询成功'): JsonResponse
    {
        return ApiResponse::page([
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage),
        ], $msg);
    }
    
    /**
     * 统一异常处理
     */
    protected function handleException(\Exception $e, string $operation = 'unknown'): JsonResponse
    {
        Log::error("操作失败: {$operation}", [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'request_id' => request()->header('X-Request-ID'),
            'user_id' => auth()->id() ?? null,
            'request_data' => request()->except(['password', 'password_confirmation']),
            'user_agent' => request()->userAgent(),
            'ip_address' => request()->ip()
        ]);
        
        return match(true) {
            $e instanceof \Illuminate\Validation\ValidationException => 
                ApiResponse::validationError('参数验证失败', $e->errors()),
            
            $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException => 
                ApiResponse::notFound('请求的资源不存在'),
            
            $e instanceof \Illuminate\Auth\AuthenticationException => 
                ApiResponse::unauthorized('身份验证失败，请重新登录'),
            
            $e instanceof \Illuminate\Auth\Access\AuthorizationException => 
                ApiResponse::forbidden('权限不足，无法执行此操作'),
            
            $e instanceof \Illuminate\Database\QueryException => 
                $this->handleDatabaseException($e),
            
            $e instanceof \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException => 
                ApiResponse::tooManyRequests('请求过于频繁，请稍后重试'),
            
            $e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException => 
                ApiResponse::notFound('请求的页面不存在'),
            
            $e instanceof \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException => 
                ApiResponse::fail('请求方法不允许', ApiResponse::BAD_REQUEST),
            
            $e instanceof \Symfony\Component\HttpKernel\Exception\HttpException => 
                $this->handleHttpException($e),
            
            default => app()->environment('production')
                ? ApiResponse::error('服务暂时不可用，请稍后重试')
                : ApiResponse::error(
                    $e->getMessage() . ' (Line: ' . $e->getLine() . ')',
                    ApiResponse::INTERNAL_SERVER_ERROR
                )
        };
    }
    
    /**
     * 处理数据库异常
     */
    protected function handleDatabaseException(\Illuminate\Database\QueryException $e): JsonResponse
    {
        // 在生产环境下，根据错误代码返回用户友好的消息
        if (app()->environment('production')) {
            $errorCode = $e->errorInfo[1] ?? 0;
            
            return match($errorCode) {
                // 唯一键冲突
                1062 => ApiResponse::fail('数据已存在，请勿重复提交', ApiResponse::UNPROCESSABLE_ENTITY),
                
                // 外键约束失败
                1451 => ApiResponse::fail('该数据正在被使用，无法删除', ApiResponse::UNPROCESSABLE_ENTITY),
                1452 => ApiResponse::fail('关联数据不存在，操作失败', ApiResponse::UNPROCESSABLE_ENTITY),
                
                // 连接失败
                2002, 2003, 2006 => ApiResponse::error('数据库连接失败，请稍后重试'),
                
                // 默认数据库错误
                default => ApiResponse::error('数据操作失败，请稍后重试')
            };
        }
        
        // 开发环境返回详细错误
        return ApiResponse::error($e->getMessage(), ApiResponse::INTERNAL_SERVER_ERROR);
    }
    
    /**
     * 处理HTTP异常
     */
    protected function handleHttpException(\Symfony\Component\HttpKernel\Exception\HttpException $e): JsonResponse
    {
        $statusCode = $e->getStatusCode();
        
        $message = match($statusCode) {
            400 => '请求参数错误',
            401 => '未授权，请先登录',
            403 => '权限不足，无法访问',
            404 => '请求的资源不存在',
            405 => '请求方法不允许',
            408 => '请求超时，请重试',
            413 => '请求数据过大',
            422 => '数据验证失败',
            429 => '请求过于频繁，请稍后重试',
            500 => '服务器错误，请稍后重试',
            502 => '网关错误，请稍后重试',
            503 => '服务暂时不可用，请稍后重试',
            504 => '网关超时，请稍后重试',
            default => app()->environment('production') 
                ? '服务暂时不可用，请稍后重试'
                : $e->getMessage()
        };
        
        return ApiResponse::fail($message, $statusCode);
    }
    
    /**
     * 验证请求参数并返回验证后的数据
     */
    protected function validateRequest(Request $request, array $rules, array $messages = []): array
    {
        return $request->validate($rules, $messages);
    }
    
    /**
     * 记录API调用日志
     */
    protected function logApiCall(string $action, array $data = []): void
    {
        Log::info("API调用: {$action}", array_merge([
            'request_id' => request()->header('X-Request-ID'),
            'user_id' => auth()->id() ?? null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'method' => request()->method(),
            'url' => request()->fullUrl()
        ], $data));
    }
}

