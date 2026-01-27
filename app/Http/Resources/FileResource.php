<?php

namespace App\Http\Resources;

use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
/**
 * @mixin File  <-- 加上这一行！注意换成你的模型路径
 */
class FileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // $this 代表当前的文件模型对象
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'is_folder' => (bool) $this->is_folder, // 强转布尔值，方便前端
            'size' => $this->size,
            'mime_type' => $this->mime_type,

            // 封装 URL 生成逻辑 (只对 public 磁盘的文件生成链接)
            'url' => $this->disk_path
                ? Storage::disk('public')->url($this->disk_path)
                : null,

            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}
