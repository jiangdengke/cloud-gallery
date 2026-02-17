<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $validKey = (string) env('API_KEY', '');

        if ($validKey === '') {
            return response()->json(['error' => 'API_KEY 未配置'], 500);
        }

        // 获取前端传来的 Key (支持从 Header 或 URL 参数获取)
        $inputKey = $request->header('X-Api-Key') ?? $request->query('key');

        if (!is_string($inputKey) || $inputKey === '' || !hash_equals($validKey, $inputKey)) {
            return response()->json(['error' => '无效的 Key'], 401);
        }

        return $next($request);
    }
}
