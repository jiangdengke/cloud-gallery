<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $validKey = env('API_KEY');
        
        // 获取前端传来的 Key (支持从 Header 或 URL 参数获取)
        $inputKey = $request->header('X-Api-Key') ?? $request->query('key');

        if ($inputKey !== $validKey) {
            return response()->json(['error' => '无效的 Key'], 401);
        }

        return $next($request);
    }
}