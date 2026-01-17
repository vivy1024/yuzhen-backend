<?php

namespace App\Exceptions;

use App\Infrastructure\Http\Responses\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }
    
    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $e
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $e)
    {
        // 如果是API请求，返回统一的JSON响应
        if ($request->expectsJson() || $request->is('api/*')) {
            return $this->renderApiException($request, $e);
        }
        
        return parent::render($request, $e);
    }
    
    /**
     * 渲染API异常响应
     */
    protected function renderApiException($request, Throwable $e): JsonResponse
    {
        return match(true) {
            $e instanceof ValidationException => 
                ApiResponse::validationError('数据验证失败', $e->errors()),
            
            $e instanceof AuthenticationException => 
                ApiResponse::unauthorized('未授权，请先登录'),
            
            $e instanceof ModelNotFoundException => 
                ApiResponse::notFound('请求的资源不存在'),
            
            $e instanceof TooManyRequestsHttpException => 
                ApiResponse::tooManyRequests('请求过于频繁，请稍后重试'),
            
            $e instanceof NotFoundHttpException => 
                ApiResponse::notFound('请求的页面不存在'),
            
            $e instanceof HttpException => 
                $this->handleHttpException($e),
            
            default => $this->handleGenericException($e)
        };
    }
    
    /**
     * 处理HTTP异常
     */
    protected function handleHttpException(HttpException $e): JsonResponse
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
     * 处理通用异常
     */
    protected function handleGenericException(Throwable $e): JsonResponse
    {
        // 生产环境返回通用错误消息
        if (app()->environment('production')) {
            return ApiResponse::error('服务暂时不可用，请稍后重试');
        }
        
        // 开发环境返回详细错误信息
        return ApiResponse::error(
            $e->getMessage() . ' (Line: ' . $e->getLine() . ' in ' . basename($e->getFile()) . ')',
            ApiResponse::INTERNAL_SERVER_ERROR
        );
    }
}

