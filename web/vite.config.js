import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// https://vite.dev/config/
export default defineConfig({
  plugins: [vue()],
  server: {
    proxy: {
      '/api': {
        // 开发环境：将 /api 代理到 Laravel（避免跨域）
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
        headers: {
            Accept: 'application/json',
        }
      }
    }
  }
})
