<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 创建分享接口的返回资源（业务代码）。
 * - token：分享标识
 * - link：分享页面地址（前端路由 /s/{token}）
 * - expired_at：过期时间（可为空）
 */
class ShareCreateResource extends JsonResource
{
    /**
     * 转换为数组结构返回给前端。
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->token,
            'link' => url('/s/' . $this->token),
            'expired_at' => $this->expired_at?->toDateTimeString(),
        ];
    }
}
