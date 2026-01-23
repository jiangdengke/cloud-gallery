<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class File extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'parent_id',  // 父文件夹 ID
        'is_folder',  // 是文件还是文件夹
        'name',       // 文件名
        'size',       // 大小
        'mime_type',  // 文件类型 (如 image/png)
        'disk_path',  // 物理路径 (用于下载)
        'hash'        // 文件哈希 (用于秒传)
    ];

    // 让数据库里的 0 和 1 自动变成代码里的 false 和 true，用起来更直观
    protected $casts = [
        'is_folder' => 'boolean',
        'size' => 'integer',
    ];
}
