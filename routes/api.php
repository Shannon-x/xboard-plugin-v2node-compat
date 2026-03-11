<?php

use Illuminate\Support\Facades\Route;
use Plugin\V2nodeCompat\Controllers\V2nodeController;
use Plugin\V2nodeCompat\Controllers\CompatManageController;

/*
|--------------------------------------------------------------------------
| V2Node 兼容路由
|--------------------------------------------------------------------------
|
| 注册 v2board 风格的管理 API，同时覆盖 V1 和 V2 路径，
| 使 v2board 前端和 Xboard 前端都能使用 V2Node 功能。
|
*/

$adminPath = admin_setting('secure_path', admin_setting('frontend_admin_path', hash('crc32b', config('app.key'))));

// V1 路径（v2board 前端使用）
Route::prefix("api/v1/{$adminPath}")->middleware(['admin', 'log'])->group(function () {
    Route::prefix('server/v2node')->group(function () {
        Route::post('save',   [V2nodeController::class, 'save']);
        Route::post('drop',   [V2nodeController::class, 'drop']);
        Route::post('update', [V2nodeController::class, 'update']);
        Route::post('copy',   [V2nodeController::class, 'copy']);
    });
    Route::get('server/manage/getNodes', [CompatManageController::class, 'getNodes']);
    Route::post('server/manage/sort',    [CompatManageController::class, 'sort']);
});

// V2 路径（Xboard 前端使用）
Route::prefix("api/v2/{$adminPath}")->middleware(['admin', 'log'])->group(function () {
    Route::prefix('server/v2node')->group(function () {
        Route::post('save',   [V2nodeController::class, 'save']);
        Route::post('drop',   [V2nodeController::class, 'drop']);
        Route::post('update', [V2nodeController::class, 'update']);
        Route::post('copy',   [V2nodeController::class, 'copy']);
    });
});
