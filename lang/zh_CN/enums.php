<?php

use App\Enums\ResponseCodeEnum;
use Jiannei\Enum\Laravel\Support\Enums\HttpStatusCode;

// 业务码中文文案（业务代码）。
// 说明：jiannei/laravel-enum 会按 `enums.{EnumClass}.{value}` 的 key 读取 message。
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
        ResponseCodeEnum::MOVE_INTO_SELF_OR_CHILD->value => '无法将文件夹移动到其自身或子文件夹中',
        ResponseCodeEnum::DOWNLOAD_FOLDER_NOT_SUPPORTED->value => '暂不支持下载文件夹',
        ResponseCodeEnum::PARENT_NOT_FOLDER->value => '目标目录不是文件夹',
        ResponseCodeEnum::FILE_NOT_FOUND->value => '文件不存在或已被删除',

        // 分享
        ResponseCodeEnum::SHARE_NOT_FOUND->value => '该分享链接不存在或已被取消',
        ResponseCodeEnum::SHARE_EXPIRED->value => '该分享已过期',
        ResponseCodeEnum::SHARE_PASSWORD_REQUIRED->value => '请输入 6 位数字提取码',
        ResponseCodeEnum::SHARE_PASSWORD_ERROR->value => '提取码错误',
        ResponseCodeEnum::SHARE_ACCESS_DENIED->value => '无权访问该资源',

        // 访问控制（公开/私有）
        ResponseCodeEnum::ACCESS_DENIED->value => '无权访问该资源',
        ResponseCodeEnum::ACCESS_PASSWORD_REQUIRED->value => '请输入 6 位数字 Key',
        ResponseCodeEnum::ACCESS_PASSWORD_ERROR->value => 'Key 错误',
        ResponseCodeEnum::ACCESS_PASSWORD_NESTED_NOT_ALLOWED->value => '不支持嵌套私有，请先取消子项私有',
        ResponseCodeEnum::ACCESS_TOO_MANY_ATTEMPTS->value => '尝试次数过多，请稍后再试',
        ResponseCodeEnum::SHARE_TOO_MANY_ATTEMPTS->value => '尝试次数过多，请稍后再试',


        ResponseCodeEnum::FILE_NOT_FOUND_ON_DISK->value => '物理文件丢失，请联系管理员',
        ResponseCodeEnum::FILE_SAVE_ERROR->value => '文件保存失败',
        ResponseCodeEnum::ZIP_CREATE_ERROR->value => '压缩包生成失败',
    ],
];
