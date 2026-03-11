<?php

// CORS 配置（业务代码）。
// 说明：当前项目为前后端分离提供跨域支持，默认仅对 /api/* 生效。
// - CORS_ALLOWED_ORIGINS 支持用逗号分隔多个来源；'*' 表示允许任意来源（开发环境方便，生产建议收敛）

$allowedOrigins = trim((string) env('CORS_ALLOWED_ORIGINS', '*'));

if ($allowedOrigins === '*') {
    $allowedOrigins = ['*'];
} else {
    // 解析形如：http://localhost:5173,https://gallery.example.com
    $allowedOrigins = array_values(array_filter(array_map(
        static fn ($origin) => trim((string) $origin),
        explode(',', $allowedOrigins)
    )));
}

return [
    // 仅对 API 路由启用 CORS
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [],

    // 允许自定义 Header（如 X-Api-Key / X-Access-Key / X-Share-Password 等）
    'allowed_headers' => ['*'],

    // 让浏览器可以读取下载相关 Header（例如从响应中拿到文件名）
    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 0,

    'supports_credentials' => false,
];
