<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\File
 */
class FileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
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
