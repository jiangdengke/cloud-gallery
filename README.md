# Cloud Gallery（云网盘）

一个基于 **Laravel 11** + **Vue 3（Vite）** 的轻量私有云盘/图库项目：支持公开浏览与下载，管理端通过 `API_KEY` 进行写入操作（上传/新建文件夹/重命名/移动/删除/创建分享）。

## 功能概览

- 公开模式：目录浏览、文件下载、图片预览、目录内 `README.md` 渲染
- 访问控制：支持「公开 / 私有」；私有内容仍会出现在列表中，但进入/下载需要 Key（6 位数字）
- 管理模式（`API_KEY`）：上传文件、新建文件夹、重命名、移动、删除、创建分享链接
- 分享链接：支持文件/文件夹分享；可选提取码（6 位数字）与过期时间；文件夹支持多级浏览；支持打包 ZIP 下载
- 秒传/去重：上传按内容哈希复用已存在物理文件；删除仅在无其他引用时删除物理文件

## 前端目录

- 前端源码在 `web/`（独立 Vite 项目）
- 生产环境构建产物会放到 Laravel 的 `public/` 下（Docker 镜像会自动完成这一步）
- 前端可通过 `VITE_API_BASE_URL` 指定后端地址（见 `web/.env.example`）

## 前后端分离（可选）

默认模式下，本项目可以“一体化部署”（Laravel 同时提供 API + 前端静态文件）。如果你希望前端独立部署（CDN/Nginx/Vercel）并让后端只提供 API，可按下述方式配置。

### 后端（API）

1) `.env` 建议配置

- `SERVE_SPA=false`（关闭 SPA 回退路由）
- `CORS_ALLOWED_ORIGINS=https://your-frontend-domain`（可多个，逗号分隔；开发常用 `http://127.0.0.1:5173`）

2) Docker 构建 API-only 镜像（不包含前端构建）

```bash
docker build --target api -t cloud-gallery-api .
```

### 前端（web）

1) 创建 `web/.env`（参考 `web/.env.example`），设置后端 API 地址（会自动补 `/api`）

```bash
# 示例：后端部署在 api.example.com
VITE_API_BASE_URL=https://api.example.com
```

2) 构建并部署静态文件

```bash
npm --prefix web run build
```

将 `web/dist/` 部署到静态站点；如果启用 history 路由（默认），需要将所有路由回退到 `index.html`。

## 运行方式

### 方式 A：Docker（推荐，接近生产）

本项目的 `docker-compose.yml` 支持 **SQLite（默认，开箱即用）** 和 **外部数据库（如 MySQL）** 两种方式：

- SQLite（推荐先用这个跑起来）：无需额外数据库服务，数据文件保存在 compose volume（`storage-data`）里
- MySQL：你需要在 `.env` 里配置 `DB_*` 连接到你自己的数据库（云 MySQL / 公司内网 MySQL / 本机 MySQL 等）

1) 准备环境变量文件

```bash
cp .env.example .env
# Windows PowerShell:
# Copy-Item .env.example .env
```

2) 修改 `.env`（至少）

- `APP_URL=http://localhost:8080`
- `API_KEY=your_secret_key`

> SQLite 方式不需要改 `DB_*`（`.env.example` 默认 `DB_CONNECTION=sqlite`）。

> 如果你要使用 MySQL，请在 `.env` 配置：
> - `DB_CONNECTION=mysql`
> - `DB_HOST=your-db-host`（数据库在宿主机时，Docker Desktop 通常可用 `host.docker.internal`）
> - `DB_PORT=3306`
> - `DB_DATABASE=cloud_gallery`
> - `DB_USERNAME=your_user`
> - `DB_PASSWORD=your_password`

3) 启动

```bash
docker compose up -d --build
```

4) 首次初始化（自动完成）

首次启动容器时会自动：

- 生成 `APP_KEY`（持久化到 `storage-data` volume）
- SQLite：自动创建数据库文件（持久化到 `storage-data` volume）
- 执行 `php artisan migrate --force`

如需关闭自动迁移，可在 `.env` 里添加 `RUN_MIGRATIONS=0`。

访问：`http://localhost:8080`

> 上传文件会持久化在 compose volume：`storage-data`（映射到容器内 `storage/`）。

### 方式 B：本地开发（Backend + Vite）

要求：PHP（>= 8.2）、Composer、Node.js（建议 18+）。

1) 安装依赖

```bash
composer install
npm --prefix web install
```

2) 准备 `.env`

```bash
cp .env.example .env
php artisan key:generate
```

3) 配置数据库并迁移

- 使用 SQLite（适合快速跑起来）：
  - 创建文件：`database/database.sqlite`
  - `.env`：`DB_CONNECTION=sqlite`
  - 执行：`php artisan migrate`
- 使用 MySQL：按需配置 `.env` 的 `DB_*`，然后执行：`php artisan migrate`

4) 启动（推荐用一个命令拉起整套开发栈）

```bash
composer run dev
```

该命令会同时启动：
- `php artisan serve`
- `php artisan queue:listen`
- `php artisan pail`（实时查看日志）
- `npm --prefix web run dev`（Vite）

开发环境前端入口是 Vite（通常是 `http://127.0.0.1:5173`），后端 API 在 `http://127.0.0.1:8000`。

## 使用说明

- 管理登录：首页右上角「管理登录」，输入 `.env` 中的 `API_KEY`
- 分享：管理模式下对文件/文件夹右键 → 分享 → 复制链接（访问路径 `/s/<token>`）
- 访问设置：管理模式下对文件/文件夹右键 → 访问设置（公开/私有，私有需 Key）

## 日志与排错

- 实时日志：`php artisan pail --timeout=0`
- 文件日志：`storage/logs/laravel.log`
- 常见的「上传失败」：
  - 没有设置/没带 `API_KEY`（管理操作会返回 401）
  - PHP 上传限制（`upload_max_filesize` / `post_max_size`）

### `php-local.ini` 有什么用？

这是一个可选的本地 PHP 配置文件示例（常用于调大上传限制、超时时间等）。本机运行时可以用：

```bash
php -c php-local.ini artisan serve
```

（Docker 镜像内已经通过 `/usr/local/etc/php/conf.d/cloud-gallery.ini` 设置了上传相关参数。）

## 清空已上传文件（开发环境）

数据由两部分组成：数据库记录（`files` / `file_shares`）+ 物理文件（`storage/app/public/uploads`）。

- 本地开发：建议直接执行 `php artisan migrate:fresh` 重新迁移；然后删除 `storage/app/public/uploads` 与 `storage/app/tmp`
- Docker：`docker compose down -v` 会清空 `storage-data`（仅文件）；数据库需要你在自己的数据库侧清理

## 测试

```bash
php artisan test
```

## License

MIT
