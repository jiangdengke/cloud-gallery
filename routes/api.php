<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FileController;
// 这是一个测试接口
Route::get('/ping', function () {
    return response()->json(['message' => 'pong']);
});

/**
 * 不需要key
 */
// 首页列表 / 进入文件夹
Route::get('/files', [FileController::class, 'index']);


/**
 * 需要key
 */
Route::middleware(['auth.key'])->group(function () {

    // 文件夹相关
    Route::post('/folders', [FileController::class, 'createFolder']); // 新建文件夹


    // 文件相关
    Route::prefix('/files')->group(function () {
        Route::get('/', [FileController::class, 'index']); // 获取列表
        Route::post('/upload', [FileController::class, 'upload']); // 文件上传
        Route::post('/rename', [FileController::class, 'rename']); // 重命名文件或文件夹
    });
});
