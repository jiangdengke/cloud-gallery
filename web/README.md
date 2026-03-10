# Cloud Gallery Web

Cloud Gallery 的前端（Vue 3 + Vite）。

## 开发

```bash
npm install
npm run dev
```

默认通过 Vite Proxy 访问后端：`/api` → `http://127.0.0.1:8000`（见 `vite.config.js`）。

## 环境变量

- `VITE_API_BASE_URL`：后端 API 地址。为空时使用同源 `/api`；前后端分离时建议设置为后端域名（如 `https://api.example.com`，会自动补 `/api`）。

示例见 `web/.env.example`。

## 构建

```bash
npm run build
```

产物在 `dist/`。
