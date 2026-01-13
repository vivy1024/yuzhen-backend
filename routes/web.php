<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AiProxyController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| AI代理路由（无/api前缀）
|--------------------------------------------------------------------------
| 前端请求: /ai/api/v1/chat/stream
| 代理到: DAML-RAG服务
*/
Route::prefix('ai')->group(function () {
    // 流式聊天接口
    Route::post('/api/v1/chat/stream', [AiProxyController::class, 'streamChat']);
    
    // 非流式聊天接口
    Route::post('/api/v1/chat', [AiProxyController::class, 'chat']);
    
    // 健康检查
    Route::get('/api/health', [AiProxyController::class, 'health']);
});
