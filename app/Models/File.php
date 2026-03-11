<?php

namespace App\Models;

/**
 * 文件/文件夹模型（业务代码）。
 *
 * 说明：
 * - files 表同时存储“文件”和“文件夹”，用 is_folder 区分
 * - parent_id 形成目录树结构（null 表示根目录）
 * - disk_path 指向 storage/public 下的物理文件路径（文件夹为空）
 * - hash 用于秒传/去重（相同内容可复用同一 disk_path）
 * - 访问控制：is_public + password_hash（私有 Key 的哈希）
 */

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class File extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * 允许批量赋值的字段。
     */
    protected $fillable = [
        'parent_id',
        'is_folder',
        'is_public',
        'password_hash',
        'name',
        'size',
        'mime_type',
        'disk_path',
        'hash',
    ];

    /**
     * 字段类型转换（便于接口输出/逻辑判断）。
     */
    protected $casts = [
        'is_folder' => 'boolean',
        'is_public' => 'boolean',
        'size' => 'integer',
    ];

    /**
     * 默认隐藏字段（避免 password_hash 被接口直接返回）。
     */
    protected $hidden = [
        'password_hash',
    ];
}
