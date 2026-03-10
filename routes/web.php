<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;

// API-only 部署时可关闭 SPA 回退（前端由独立站点/CDN 托管）
if (!filter_var(env('SERVE_SPA', true), FILTER_VALIDATE_BOOLEAN)) {
    Route::get('/', function () {
        return response()->json([
            'name' => 'Cloud Gallery API',
            'status' => 'ok',
        ]);
    });

    return;
}

// 任何未被 API 匹配的路由，都返回前端入口文件 (SPA 支持)
Route::get('/{any?}', function () {
    $path = public_path('index.html');
    if (!File::exists($path)) {
        return "前端文件未生成 (public/index.html 缺失)";
    }
    return File::get($path);
})->where('any', '^(?!api).*$'); // 排除 /api 开头的请求
