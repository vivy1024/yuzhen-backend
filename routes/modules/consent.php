<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ConsentController;

/**
 * Consent Module Routes
 *
 * 协议同意记录路由
 *
 * 前缀: /api/consent
 */

Route::prefix('consent')->middleware(['jwt.auth'])->group(function () {
    Route::post('/record', [ConsentController::class, 'store']);
    Route::get('/latest', [ConsentController::class, 'latest']);
});
