<?php

use Illuminate\Support\Facades\Route;

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

// Prometheus metrics 端点（无需认证，通过网络策略限制访问）
Route::get('/metrics', [\App\Http\Controllers\PrometheusMetricsController::class, 'metrics']);

// AI代理路由已移至 routes/api.php（使用api中间件组，避免CORS和CSRF问题）
