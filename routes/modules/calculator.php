<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 计算器路由（无需认证，独立限流）
|--------------------------------------------------------------------------
| POST /api/calculators/tdee          TDEE计算
| POST /api/calculators/ffmi          FFMI计算
| POST /api/calculators/one-rm        1RM估算
| POST /api/calculators/intensity     RPE/RIR/%1RM转换
| POST /api/calculators/weight        训练重量推荐
| POST /api/calculators/carb-cycling  碳循环计划
| POST /api/calculators/macros        宏量营养素分配
*/

Route::prefix('calculators')->middleware('throttle:calculators')->group(function () {
    Route::post('tdee', [\App\Http\Controllers\Api\CalculatorController::class, 'tdee']);
    Route::post('ffmi', [\App\Http\Controllers\Api\CalculatorController::class, 'ffmi']);
    Route::post('one-rm', [\App\Http\Controllers\Api\CalculatorController::class, 'oneRM']);
    Route::post('intensity', [\App\Http\Controllers\Api\CalculatorController::class, 'intensity']);
    Route::post('weight', [\App\Http\Controllers\Api\CalculatorController::class, 'weight']);
    Route::post('carb-cycling', [\App\Http\Controllers\Api\CalculatorController::class, 'carbCycling']);
    Route::post('macros', [\App\Http\Controllers\Api\CalculatorController::class, 'macros']);
});
