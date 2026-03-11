<?php

namespace App\Enums;

/**
 * 接口响应业务码（业务代码）。
 *
 * 约定：
 * - 20000：成功
 * - 30000~39999：业务错误（可提示给用户）
 * - 50000+：服务端错误（如物理文件缺失、打包失败等）
 *
 * 提示文案对应：lang/zh_CN/enums.php
 */

use Jiannei\Enum\Laravel\Support\Traits\EnumEnhance;

enum ResponseCodeEnum: int
{
    // 引入 Trait：自动提供 value()/message() 等能力（由 jiannei/laravel-enum 提供）
    use EnumEnhance;

    // ============================================
    // 业务逻辑码
    // ============================================
    case OK = 20000;

    // 网盘项目专属码 (30000 - 39999)
    case FOLDER_ALREADY_EXISTS = 30001;
    case FILE_TOO_LARGE = 30002;
    case INVALID_KEY = 30003; // 管理员 API Key 无效
    case NAME_ALREADY_EXISTS = 30004;

    case MOVE_INTO_SELF_OR_CHILD = 30005;
    case DOWNLOAD_FOLDER_NOT_SUPPORTED = 30006;
    case PARENT_NOT_FOLDER = 30011;
    case FILE_NOT_FOUND = 30012;
    case SHARE_ACCESS_DENIED = 30013;
    case ACCESS_DENIED = 30014;
    case ACCESS_PASSWORD_REQUIRED = 30015;
    case ACCESS_PASSWORD_ERROR = 30016;
    case ACCESS_PASSWORD_NESTED_NOT_ALLOWED = 30017;
    case ACCESS_TOO_MANY_ATTEMPTS = 30018; // 私有 Key 尝试次数过多（限流）
    case SHARE_TOO_MANY_ATTEMPTS = 30019;  // 分享提取码尝试次数过多（限流）
    case FILE_SAVE_ERROR = 50001;
    case FILE_NOT_FOUND_ON_DISK = 50002;
    case ZIP_CREATE_ERROR = 50003;

    // 分享相关
    case SHARE_NOT_FOUND = 30007;          // 链接不存在
    case SHARE_EXPIRED = 30008;            // 链接已过期
    case SHARE_PASSWORD_REQUIRED = 30009;  // 需要提取码
    case SHARE_PASSWORD_ERROR = 30010;     // 提取码错误
}
