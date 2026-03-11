<?php

namespace App\Http\Middleware;

/**
 * 管理员 API Key 鉴权中间件（业务代码）。
 *
 * 用途：
 * - 保护“写操作/管理操作”接口（routes/api.php 中通过 auth.key 组启用）
 *
 * 约定：
 * - 优先从 Header `X-Api-Key` 读取
 * - 兼容旧版从 query `?key=` 读取
 */

use App\Enums\ResponseCodeEnum;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Jiannei\Response\Laravel\Support\Facades\Response as ApiResponse;
use Symfony\Component\HttpFoundation\Response;

class CheckApiToken
{
    /**
     * 处理请求：验证 API_KEY 是否配置、是否匹配。
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 从环境变量读取管理员 Key（为空时视为未配置）
        $validKey = trim((string) env('API_KEY', ''));

        if ($validKey === '') {
            return ApiResponse::fail('API_KEY is not configured', 500);
        }

        // 请求端传入的 Key：优先 Header，兼容 query
        $inputKey = $request->header('X-Api-Key') ?? $request->query('key');
        $inputKey = is_string($inputKey) ? trim($inputKey) : '';

        if ($inputKey === '' || !hash_equals($validKey, $inputKey)) {
            // 未授权：优先使用枚举对应的中文文案（lang/zh_CN/enums.php）
            $messageKey = 'enums.' . ResponseCodeEnum::class . '.' . ResponseCodeEnum::INVALID_KEY->value;
            $message = Lang::has($messageKey) ? Lang::get($messageKey) : 'Invalid API key';

            return ApiResponse::errorUnauthorized($message);
        }

        // 鉴权通过，继续执行后续中间件/控制器
        return $next($request);
    }
}
