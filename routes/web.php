<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;

// Web 路由（业务代码）。
// 说明：本项目默认使用 SPA（Vue）构建产物 public/index.html 作为前端入口。
// 若需要“前后端完全分离”部署，可在 .env 设置 SERVE_SPA=false，让 Laravel 仅提供 API。

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
        // 前端未构建时的兜底提示（便于排查部署问题）
        return "前端文件未生成 (public/index.html 缺失)";
    }
    return File::get($path);
})->where('any', '^(?!api).*$'); // 排除 /api 开头的请求
