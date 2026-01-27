<?php

namespace App\Enums;

use Jiannei\Enum\Laravel\Support\Traits\EnumEnhance;

enum ResponseCodeEnum: int
{
    // 👇 核心：引入这个 Trait，它会自动帮你实现 value() 和 message() 方法
    // 你不需要再手写 message() 了！
    use EnumEnhance;

    // ============================================
    // 业务逻辑码
    // ============================================
    case OK = 20000;

    // 网盘项目专属码 (30000 - 39999)
    case FOLDER_ALREADY_EXISTS = 30001;
    case FILE_TOO_LARGE = 30002;
    case INVALID_KEY = 30003;
    case NAME_ALREADY_EXISTS = 30004;

    case MOVE_INTO_SELF_OR_CHILD = 30005;
    case DOWNLOAD_FOLDER_NOT_SUPPORTED = 30006;
    case FILE_SAVE_ERROR = 50001;
}
