<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class File extends Model
{
    use HasFactory, SoftDeletes;

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

    protected $casts = [
        'is_folder' => 'boolean',
        'is_public' => 'boolean',
        'size' => 'integer',
    ];

    protected $hidden = [
        'password_hash',
    ];
}

