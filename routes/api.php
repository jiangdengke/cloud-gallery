<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FileController;

/**
 * API 路由（业务代码）。
 *
 * 约定：
 * - 公开接口：不需要管理员 API Key（但访问私有资源可能需要 6 位数字 Key）
 * - 管理接口：统一走 auth.key 中间件（Header: X-Api-Key）
 */

// 健康检查/联通性测试
Route::get('/ping', function () {
    return response()->json(['message' => 'pong']);
});

// ---------------------------
// 公开接口（不需要 auth.key）
// ---------------------------
Route::get('/files', [FileController::class, 'index']);
Route::get('/files/{id}', [FileController::class, 'detail']); // 文件详情
Route::get('/files/{id}/download', [FileController::class, 'download']); // 直接下载入口（兼容）

// 两段式下载：先拿短期签名 URL，再跳转下载（避免 Key 暴露在 URL）
Route::post('/files/{id}/download-url', [FileController::class, 'downloadUrl']);
Route::get('/files/{id}/download-signed', [FileController::class, 'signedDownload'])
    ->middleware(\Illuminate\Routing\Middleware\ValidateSignature::class)
    ->name('files.download.signed'); // 真实下载入口（签名校验）

// 分享：游客可访问（可选提取码）
Route::prefix('shares')->group(function () {
    // 查看信息
    Route::get('/{token}', [\App\Http\Controllers\ShareController::class, 'detail']);

    // 新增：下载文件
    Route::get('/{token}/download', [\App\Http\Controllers\ShareController::class, 'download']);
    Route::get('/{token}/files', [\App\Http\Controllers\ShareController::class, 'fileList']); // 游客查看文件夹列表
});

// ---------------------------
// 管理接口（需要 auth.key）
// ---------------------------
Route::middleware(['auth.key'])->group(function () {

    // 文件夹相关
    Route::post('/folders', [FileController::class, 'createFolder']); // 新建文件夹


    // 文件相关
    Route::prefix('/files')->group(function () {
        // Route::get('/', [FileController::class, 'index']); // 获取列表 (已在外部定义为公开)
        Route::post('/upload', [FileController::class, 'upload']); // 文件上传
        Route::post('/rename', [FileController::class, 'rename']); // 重命名文件或文件夹
        Route::delete('/delete', [FileController::class, 'delete']); // 删除
        Route::post('/move', [FileController::class, 'move']); // 移动文件或文件夹
        Route::post('/access', [FileController::class, 'updateAccess']); // 访问控制（公开/私有/提取码）
    });


    // 分享管理
    Route::post('/shares/create', [\App\Http\Controllers\ShareController::class, 'create']);
    Route::delete('/shares/{id}', [\App\Http\Controllers\ShareController::class, 'destroy']);
});
