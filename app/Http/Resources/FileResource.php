<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\File
 *
 * 文件/文件夹资源格式化（业务代码）。
 * - is_public / is_protected 用于前端展示与交互（是否需要 Key）
 */
class FileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // 公开的判定：is_public=true 且未设置 password_hash
        $isPublic = (bool) ($this->is_public && ($this->password_hash === null || $this->password_hash === ''));

        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'is_folder' => (bool) $this->is_folder,
            'is_public' => $isPublic,
            'is_protected' => !$isPublic,
            'size' => $this->size,
            'mime_type' => $this->mime_type,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
