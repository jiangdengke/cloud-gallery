<?php

namespace App\Http\Middleware;

use App\Enums\ResponseCodeEnum;
use Closure;
use Illuminate\Http\Request;
use Jiannei\Response\Laravel\Support\Facades\Response as ApiResponse;
use Symfony\Component\HttpFoundation\Response;

class CheckApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $validKey = trim((string) env('API_KEY', ''));

        if ($validKey === '') {
            return ApiResponse::fail('API_KEY 未配置', 500);
        }

        $inputKey = $request->header('X-Api-Key') ?? $request->query('key');
        $inputKey = is_string($inputKey) ? trim($inputKey) : '';

        if ($inputKey === '' || !hash_equals($validKey, $inputKey)) {
            return ApiResponse::errorUnauthorized(ResponseCodeEnum::INVALID_KEY->message());
        }

        return $next($request);
    }
}
