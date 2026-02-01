# Cloud Gallery (云网盘)

一个基于 **Laravel 11** 和 **Vue 3** 构建的轻量级私有云网盘系统。
设计灵感来源于 OpenList，支持公开目录浏览与后台文件管理分离，具备 Markdown 渲染、图片预览、拖拽上传等现代网盘功能。

## ✨ 功能特性

*   **前台/后台分离**：
    *   **公开浏览**：默认首页为只读模式，访客可浏览目录、下载文件、预览图片和 Markdown 文档。
    *   **管理后台**：通过 API Key 登录后进入管理模式，拥有完全控制权。
*   **文件管理**：
    *   📂 **多级目录**：支持无限级文件夹创建与导航。
    *   📤 **文件上传**：支持多文件拖拽上传，已优化大文件上传配置。
    *   ✏️ **文件操作**：支持重命名、移动、彻底删除。
    *   ⬇️ **下载**：支持文件直接下载，右键菜单快速操作。
*   **预览体验**：
    *   🖼️ **图片预览**：点击图片文件直接查看大图。
    *   📝 **Markdown 渲染**：目录内若存在 `README.md`，自动在列表下方渲染展示（类似 GitHub）。
*   **交互体验**：
    *   基于 Ant Design Vue 的现代化 UI。
    *   支持右键上下文菜单（Context Menu）。
    *   清晰的面包屑导航。

## 🛠 技术栈

### 后端 (Backend)
*   **Framework**: Laravel 11.x
*   **Language**: PHP >= 8.2
*   **Database**: MySQL 8.0+
*   **API Response**: `jiannei/laravel-response`

### 前端 (Frontend)
*   **Framework**: Vue 3 (Composition API)
*   **Build Tool**: Vite
*   **UI Library**: Ant Design Vue 4.x
*   **Network**: Axios
*   **Routing**: Vue Router

## 🚀 部署指南

### 1. 后端环境搭建 (Laravel)

确保你的环境满足 Laravel 11 要求 (PHP >= 8.2, Composer)。

```bash
# 1. 安装依赖
composer install

# 2. 复制环境变量
cp .env.example .env

# 3. 配置数据库与 API Key
# 编辑 .env 文件：
# DB_HOST=127.0.0.1
# DB_DATABASE=cloud_gallery
# API_KEY=your_secret_password  <-- 设置你的管理后台登录密码

# 4. 生成密钥
php artisan key:generate

# 5. 建立存储软链 (关键！否则无法访问文件)
php artisan storage:link

# 6. 数据库迁移
php artisan migrate

# 7. (可选) 修改上传限制
# 如果使用内置服务器，建议创建一个 php-local.ini 并修改 upload_max_filesize
# 或者直接修改 php.ini
```

### 2. 前端环境搭建 (Vue)

前端代码位于 `web/` 目录下。

```bash
cd web

# 1. 安装依赖
npm install

# 2. 开发模式运行 (依赖后端 8000 端口)
npm run dev
# 访问 http://localhost:5173

# 3. 生产环境构建
npm run build
# 构建产物位于 web/dist/，可部署到 Nginx 或复制到 Laravel public 目录
```

### 3. Nginx 部署建议 (生产环境)

建议使用 Nginx 反向代理，将前端和后端整合到同一域名下。

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/cloud-gallery/public; # Laravel public 目录

    index index.php index.html;

    # 后端 API 转发
    location /api {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # 前端 (如果构建后放在 Laravel public 下)
    # 或者单独部署前端，配置 / 为前端静态文件
    
    location ~ \.php$ {
        # PHP-FPM 配置...
    }
}
```

## ⚙️ 配置说明

### API Key 权限
在 `.env` 中设置 `API_KEY`。前端点击右上角“管理登录”，输入此 Key 即可获得管理员权限（上传、删除、移动等）。

### 大文件上传
若上传大文件失败，请检查 `php.ini` 配置：
```ini
upload_max_filesize = 100M
post_max_size = 100M
```

## 📂 目录结构

```
/
├── app/                 # Laravel 后端核心代码
├── database/            # 数据库迁移文件
├── public/              # 静态资源入口
├── storage/             # 文件存储区域 (app/public/uploads)
├── routes/              # API 路由定义 (api.php)
└── web/                 # Vue 前端项目
    ├── src/
    │   ├── api/         # API 请求封装
    │   ├── components/  # FileExplorer 等核心组件
    │   ├── views/       # Home(前台) 和 Admin(后台) 页面
    │   └── router/      # 路由配置
    └── vite.config.js   # Vite 配置 (含 API 代理)
```

## 📄 License

MIT License.