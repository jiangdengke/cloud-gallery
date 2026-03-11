<?php

namespace App\Http\Resources;

use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\FileShare
 *
 * 分享详情资源（业务代码）。
 * - 聚合分享记录与关联文件信息，供分享落地页渲染
 */
class ShareDetailResource extends JsonResource
{
    /**
     * 转换为数组结构返回给前端。
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // 关联的文件/文件夹（可能为空：例如目标被删除）
        /** @var File|null $file */
        $file = $this->file;

        return [
            'share_token' => $this->token,
            'file_id' => $file?->id,
            'name' => $file?->name,
            'is_folder' => (bool) ($file?->is_folder ?? false),
            'size' => $file?->size,
            'created_at' => $this->created_at?->toDateTimeString(),
            'expired_at' => $this->expired_at?->toDateTimeString(),
            'download_url' => '/api/shares/' . $this->token . '/download',
        ];
    }
}
