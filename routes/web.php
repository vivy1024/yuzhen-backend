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

// Prometheus metrics 端点（SEC-3: 仅允许内部网络访问）
Route::get('/metrics', [\App\Http\Controllers\PrometheusMetricsController::class, 'metrics'])
    ->middleware('internal.network');

// AI代理路由已移至 routes/api.php（使用api中间件组，避免CORS和CSRF问题）
