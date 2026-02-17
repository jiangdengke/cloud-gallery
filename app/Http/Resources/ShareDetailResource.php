<?php

namespace App\Http\Resources;

use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\FileShare
 */
class ShareDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
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
