<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// 应用入口配置（业务代码）。
// 这里主要做两件事：
// 1) 挂载 routes/web.php 与 routes/api.php
// 2) 注册 CORS 与自定义中间件别名（auth.key）

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // 允许浏览器跨域访问 /api（config/cors.php 可配置允许的来源）
        $middleware->append(\Illuminate\Http\Middleware\HandleCors::class);

        // 注册中间件别名：用于保护管理接口
        $middleware->alias([
            'auth.key' => App\Http\Middleware\CheckApiToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
