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
    
    // 新建文件夹
    Route::post('/folders', [FileController::class, 'createFolder']);
    
    // 下一步我们会在这里加 /upload 接口
});