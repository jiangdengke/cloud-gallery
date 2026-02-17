<?php

namespace App\Http\Middleware;

use App\Enums\ResponseCodeEnum;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Jiannei\Response\Laravel\Support\Facades\Response as ApiResponse;
use Symfony\Component\HttpFoundation\Response;

class CheckApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $validKey = trim((string) env('API_KEY', ''));

        if ($validKey === '') {
            return ApiResponse::fail('API_KEY is not configured', 500);
        }

        $inputKey = $request->header('X-Api-Key') ?? $request->query('key');
        $inputKey = is_string($inputKey) ? trim($inputKey) : '';

        if ($inputKey === '' || !hash_equals($validKey, $inputKey)) {
            $messageKey = 'enums.' . ResponseCodeEnum::class . '.' . ResponseCodeEnum::INVALID_KEY->value;
            $message = Lang::has($messageKey) ? Lang::get($messageKey) : 'Invalid API key';

            return ApiResponse::errorUnauthorized($message);
        }

        return $next($request);
    }
}
