# Cloud Gallery（云网盘）

一个基于 **Laravel 11** + **Vue 3** 的轻量私有云盘/图集系统。支持公开浏览与管理后台分离，包含分享链接、图片/Markdown 预览、拖拽上传等能力。

## ✨ 功能概览

- **公开模式**：目录浏览、文件下载、图片预览、Markdown 渲染（目录内存在 `README.md` 时自动展示）。
- **管理模式（API_KEY）**：上传文件、创建文件夹、重命名、移动、删除、创建分享链接。
- **分享链接**：支持分享文件/文件夹；可设置提取码（4–6 位）和过期时间；文件夹支持多级浏览。
- **下载**：文件直接下载；**文件夹在线打包为 ZIP 下载**。
- **秒传/去重**：上传按文件哈希复用已存在的物理文件；删除时仅在无其它引用时删除物理文件。

## 🛠 技术栈

- **Backend**：Laravel 11.x、PHP >= 8.2、MySQL 8.0+（Docker 默认）/ SQLite（可选）
- **Frontend**：Vue 3、Vite、Ant Design Vue、Axios、Vue Router
- **响应封装**：`jiannei/laravel-response`

## 🧭 前端目录说明

- 前端源码仅在 `web/`（Vite 项目）。
- 根目录不再保留 Laravel 默认的 `resources/js`、`resources/css` 以及 Vite/Tailwind 相关配置（避免两套前端并存）。
- 生产构建会把 `web/dist` 产物复制到 `public/`，由 `routes/web.php` 将非 `/api` 的请求回落到 `public/index.html`（SPA）。

## 🚀 快速开始（本地开发）

### 1) 后端（Laravel）

```bash
composer install

# 复制配置（.env 不会提交到仓库）
cp .env.example .env
# Windows PowerShell:
# Copy-Item .env.example .env

# 生成 APP_KEY
php artisan key:generate

# 建立存储软链（关键：用于访问 public/storage）
php artisan storage:link

# 迁移数据库（按 .env 配置的 DB 执行）
php artisan migrate

# 启动后端（默认 http://127.0.0.1:8000）
php artisan serve
```

`.env` 至少需要配置：

```ini
APP_URL=http://localhost:5173
API_KEY=your_secret_key

# 本地可选：sqlite / mysql
DB_CONNECTION=sqlite
```

> 说明：本项目的“分享链接”会使用 `APP_URL` 生成链接。开发模式下前端通常在 `5173`，建议把 `APP_URL` 设为前端地址（如上）。Docker/生产环境请设为实际域名/端口。
>
> 使用 SQLite 时，还需要创建数据库文件并确保启用 `pdo_sqlite` 扩展：
>
> - macOS/Linux：`touch database/database.sqlite`
> - Windows PowerShell：`New-Item -ItemType File -Path database/database.sqlite -Force`

### 2) 前端（Vue）

```bash
# 方式 A：进入 web 目录
cd web
npm install
npm run dev

# 方式 B：在项目根目录执行
# npm --prefix web install
# npm --prefix web run dev
```

访问：`http://localhost:5173`

### 3) 构建到同一域名（非 Docker 生产部署）

Laravel 的 `routes/web.php` 会把非 `/api` 请求回落到 `public/index.html`（SPA）。生产环境需要把前端构建产物放到 `public/`：

```bash
cd web
npm run build

# 将 web/dist/ 复制到 public/（确保生成 public/index.html）
cp -r dist/* ../public/
# Windows PowerShell:
# Copy-Item -Recurse -Force dist/* ../public/
```

## 🐳 Docker 部署（推荐）

Dockerfile 会自动构建前端并复制到 `public/`，无需手动构建。

> 注意：`docker-compose.yml` **不包含数据库服务**。请在 `.env` 中配置 `DB_*` 连接到你自己的数据库（例如云 MySQL、公司内网 MySQL 等）。
>
> - 数据库在宿主机：`DB_HOST=host.docker.internal`（Docker Desktop 通常可用）
> - 数据库在远程：填公网/内网地址即可

1) 准备 `.env`（示例）：

```ini
APP_URL=http://localhost:8080
API_KEY=your_secret_key

DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_PORT=3306
DB_DATABASE=cloud_gallery
DB_USERNAME=your_user
DB_PASSWORD=your_password
```

2) 启动：

```bash
docker compose up -d --build
```

3) 初始化（首次启动需要生成 `APP_KEY` 并迁移数据库）：

```bash
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
```

访问：`http://localhost:8080`

> `docker-compose.yml` 仅负责启动应用容器；上传文件会持久化在 volume：`storage-data`。

## ✅ 使用说明

- **管理登录**：首页右上角“管理登录”输入 `.env` 里的 `API_KEY`。
- **创建分享**：管理模式下，对文件/文件夹右键 → **分享** → 复制链接。
- **访问分享**：打开 `/s/<token>`；如设置了提取码会提示输入。  
  - 分享文件夹：支持面包屑导航与“下载此文件夹”（ZIP）。  
  - 分享文件：支持下载；图片/Markdown 在分享页可直接预览。

## 🔌 API 速查

### 公共接口（无需 Key）

- `GET /api/ping`
- `GET /api/files?parent_id=<id|null>`
- `GET /api/files/{id}`
- `GET /api/files/{id}/download`（若 `{id}` 为文件夹，返回 ZIP）
- `GET /api/shares/{token}?password=<optional>`
- `GET /api/shares/{token}/files?parent_id=<folderId>&password=<optional>`（支持多级目录）
- `GET /api/shares/{token}/download?file_id=<id>&password=<optional>`（可下载子文件/子文件夹；文件夹返回 ZIP）

### 管理接口（需要 `X-Api-Key: <API_KEY>`）

- `POST /api/files/upload`（multipart：`file`、可选 `parent_id`）
- `POST /api/folders`（`name`、可选 `parent_id`）
- `POST /api/files/rename`（`id`、`name`）
- `POST /api/files/move`（`id`、`parent_id`）
- `DELETE /api/files/delete`（`ids[]`）
- `POST /api/shares/create`（`file_id`、可选 `password`、可选 `expired_at`）
- `DELETE /api/shares/{id}`

## 📦 存储与去重说明

- 元数据：`files`、`file_shares` 表。
- 物理文件：默认写入 `storage/app/public/uploads/<Y-m-d>/...`。
- `public/storage` 通过 `php artisan storage:link` 映射到 `storage/app/public`。
- 去重：上传会按内容哈希复用已有 `disk_path`；删除会在没有其它引用时再删除物理文件。
- ZIP：文件夹下载时临时生成 ZIP（`storage/app/tmp`），响应发送后自动删除。

## 🧪 测试

```bash
php artisan test
```

> 说明：Feature 测试依赖可用的数据库驱动与配置（例如 SQLite 需要启用 `pdo_sqlite` 扩展）。

## 📂 目录结构

```
/
├── app/                  # Laravel 后端核心代码
├── database/             # 迁移/工厂/种子
├── public/               # Web 入口（生产需包含 index.html）
├── routes/               # 路由（api.php / web.php）
├── storage/              # 文件存储（app/public/uploads）
└── web/                  # Vue 前端项目（Vite）
```

## 📄 License

MIT License.
