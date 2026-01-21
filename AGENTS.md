# Repository Guidelines

## 项目结构与模块组织
- `app/` 存放应用核心代码（控制器、模型、任务等）。
- `routes/` 定义路由（`web.php`、`api.php`、`console.php`）。
- `resources/` 用于视图与前端资源（Blade、CSS、JS），Vite 构建输出到 `public/`。
- `database/` 包含迁移、工厂、种子；默认使用 `database/database.sqlite`。
- `tests/` 放置 PHPUnit 测试（`Feature/` 与 `Unit/`）。
- `config/`、`bootstrap/`、`storage/`、`public/` 遵循 Laravel 约定。

## 构建、测试与开发命令
- `composer install` 安装 PHP 依赖。
- `npm install` 安装前端依赖。
- `php artisan serve` 启动本地 PHP 服务。
- `composer run dev` 启动完整开发栈（服务、队列、日志、Vite）。
- `npm run dev` 启动 Vite 开发服务器。
- `npm run build` 构建生产资源。
- `php artisan migrate` 执行数据库迁移。

## 编码风格与命名规范
- 缩进 4 空格，LF 换行（见 `.editorconfig`）。
- PHP 类使用 StudlyCase，命名空间符合 PSR-4（如 `App\\Http\\Controllers`）。
- 测试文件命名为 `*Test.php`，放在 `tests/Feature` 或 `tests/Unit`。
- 格式化工具为 Laravel Pint：`./vendor/bin/pint`。

## 测试指南
- 主要测试入口为 PHPUnit：`php artisan test` 或 `./vendor/bin/phpunit`。
- 行为优先：HTTP 流程用 Feature，纯逻辑用 Unit。

## 提交与 PR 指南
- 暂无固定提交规范（目前仅有 `first commit`）。建议使用简短祈使句（如“Add gallery upload validation”）。
- PR 需包含变更说明、验证步骤与关联 Issue；涉及 UI 需截图或录屏。

## 配置与安全提示
- 复制 `.env.example` 为 `.env`，并执行 `php artisan key:generate`。
- 不提交密钥；上传文件放 `storage/`，需要对外访问时使用 `php artisan storage:link`。
