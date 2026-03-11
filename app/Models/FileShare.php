<?php

namespace App\Models;

/**
 * 分享记录模型（业务代码）。
 *
 * 字段说明（简要）：
 * - file_id：被分享的文件/文件夹
 * - token：分享标识（用于 URL）
 * - password：可选 6 位数字提取码（为空表示公开分享）
 * - expired_at：可选过期时间（为空表示永久）
 * - click_count：浏览次数统计
 */

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileShare extends Model
{
    /**
     * 允许批量赋值的字段。
     */
    protected $fillable = [
        'file_id',
        'token',
        'password',
        'expired_at',
        'click_count',
    ];

    /**
     * 字段类型转换。
     */
    protected $casts = [
        'expired_at' => 'datetime',
    ];

    /**
     * 关联：分享指向的文件/文件夹。
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }
}
