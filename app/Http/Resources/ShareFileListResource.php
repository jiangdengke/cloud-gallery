<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShareFileListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
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

