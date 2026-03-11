<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 分享文件夹列表资源（业务代码）。
 * - list：子项列表（由 ShareFileResource 格式化）
 * - parent_id：当前目录 id
 * - root_id：分享根目录 id（用于前端判断范围）
 */
class ShareFileListResource extends JsonResource
{
    /**
     * 转换为数组结构返回给前端。
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // 资源传入结构：['list' => ..., 'parent_id' => ..., 'root_id' => ...]
        $list = data_get($this->resource, 'list', []);
        $parentId = data_get($this->resource, 'parent_id');
        $rootId = data_get($this->resource, 'root_id');

        return [
            'list' => ShareFileResource::collection($list)->toArray($request),
            'parent_id' => $parentId,
            'root_id' => $rootId,
        ];
    }
}
