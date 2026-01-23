# Cloud Gallery (云网盘后端)

基于 Laravel 11 构建的网盘系统后端 API。

## 🛠 技术栈

* **Framework**: Laravel 11.x
* **Language**: PHP >= 8.2
* **Database**: MySQL 8.0+
* **Response**: `jiannei/laravel-response` (统一响应格式)
* **Enum**: `jiannei/laravel-enum` (业务状态码与国际化)

## 🚀 快速开始 (开发指南)

如果你刚把项目 `git clone` 下来，请按以下步骤初始化开发环境。

### 1. 安装依赖

```bash
composer install

```

### 2. 环境配置

复制环境变量示例文件：

```bash
cp .env.example .env

```

打开 `.env` 文件，配置数据库和语言环境：

```ini
APP_NAME="Cloud Gallery"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

# ⚠️ 关键：数据库配置
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cloud_gallery  # 请确保数据库已手动创建
DB_USERNAME=root
DB_PASSWORD=your_password

# ⚠️ 关键：语言配置 (本项目默认使用中文)
APP_LOCALE=zh_CN
APP_FALLBACK_LOCALE=zh_CN

```

生成应用密钥：

```bash
php artisan key:generate

```

### 3. 数据库迁移

初始化数据表结构：

```bash
php artisan migrate

```

### 4. 生成 IDE 提示 (可选，推荐)

为了让 VS Code 等编辑器能正确提示模型字段和魔术方法：

```bash
# 生成 Facade 和 Model 提示
php artisan ide-helper:generate
php artisan ide-helper:models -N

```

### 5. 启动服务

```bash
php artisan serve

```

访问 `http://127.0.0.1:8000/api/files` 测试接口。

---

## 📝 开发规范

本项目使用了 **统一响应结构** 和 **枚举管理状态码**，请严格遵守以下开发流程。

### 1. 响应格式

所有 API 均返回统一的 JSON 结构：

``` json
{
  "status": 200,          // HTTP 状态码
  "code": 20000,          // 业务状态码 (Enum定义)
  "message": "操作成功",   // 提示消息 (自动翻译)
  "data": { ... },        // 业务数据
  "error": {}             // 调试错误信息 (生产环境隐藏)
}

```
