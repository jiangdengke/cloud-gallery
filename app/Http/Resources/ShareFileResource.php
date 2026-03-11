<?php

namespace App\Http\Resources;

use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin File
 *
 * 分享列表中的文件项资源（业务代码）。
 * 说明：不返回私有访问控制字段（分享侧按提取码统一保护）。
 */
class ShareFileResource extends JsonResource
{
    /**
     * 转换为数组结构返回给前端。
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'is_folder' => (bool) $this->is_folder,
            'size' => $this->size,
            'mime_type' => $this->mime_type,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
