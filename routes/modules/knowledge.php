<?php

use App\Http\Controllers\Api\KnowledgeController;
use Illuminate\Support\Facades\Route;

Route::prefix('knowledge')->group(function () {
    Route::get('/categories', [KnowledgeController::class, 'categories']);
    Route::get('/cards',      [KnowledgeController::class, 'cards']);
    Route::get('/search',     [KnowledgeController::class, 'search']);
    Route::get('/',           [KnowledgeController::class, 'index']);
    Route::get('/{id}',       [KnowledgeController::class, 'show'])->whereNumber('id');
});
