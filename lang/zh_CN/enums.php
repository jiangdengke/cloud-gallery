<?php

use App\Enums\ResponseCodeEnum;
use Jiannei\Enum\Laravel\Support\Enums\HttpStatusCode;
return [
    // 必须使用类名作为 Key，这样 laravel-enum 才能自动找到
    ResponseCodeEnum::class => [
        // 标准 HTTP 状态码
        HttpStatusCode::HTTP_OK->value => '操作成功',
        HttpStatusCode::HTTP_UNAUTHORIZED->value => '授权失败',
        // 这里的 Key 是 Enum 的 value (数字)
        ResponseCodeEnum::OK->value => '操作成功',

        // 网盘业务
        ResponseCodeEnum::FOLDER_ALREADY_EXISTS->value => '该目录下已存在同名文件夹',
        ResponseCodeEnum::FILE_TOO_LARGE->value => '文件大小超出限制',
        ResponseCodeEnum::INVALID_KEY->value => '访问口令(Key)无效或已过期',
        ResponseCodeEnum::NAME_ALREADY_EXISTS->value => '该名称已存在，请换一个名字',

        ResponseCodeEnum::FILE_SAVE_ERROR->value => '文件保存失败',
    ],
];
