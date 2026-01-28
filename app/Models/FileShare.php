<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class FileShare extends Model
{
    protected $fillable = [
        'file_id',
        'token',
        'password',
        'expired_at',
        'click_count',
    ];
    protected $casts = [
        'expired_at' => 'datetime',
    ];

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }
}
