<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 文件列表资源包装（业务代码）。
 *
 * 说明：
 * - 统一控制列表接口返回字段结构
 * - list 内的每一项由 FileResource 负责格式化
 */
class FileListResource extends JsonResource
{
    /**
     * 转换为数组结构返回给前端。
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // 资源传入结构：['list' => ..., 'parent_id' => ...]
        $list = data_get($this->resource, 'list', []);
        $parentId = data_get($this->resource, 'parent_id');

        return [
            'list' => FileResource::collection($list)->toArray($request),
            'parent_id' => $parentId,
        ];
    }
}
