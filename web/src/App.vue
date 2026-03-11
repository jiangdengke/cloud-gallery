<template>
  <a-config-provider
    :theme="{
      algorithm: isDark ? theme.darkAlgorithm : theme.defaultAlgorithm,
      token: {
        colorPrimary: '#1890ff',
      }
    }"
  >
    <div :class="{ 'app-dark': isDark }">
      <router-view />
    </div>
  </a-config-provider>
</template>

<script setup>
// App 根组件（前端业务代码）
// - 使用 Ant Design Vue 的 ConfigProvider 统一主题算法
// - isDark 由 themeState 管理并写入 body class
import { theme } from 'ant-design-vue';
import { isDark } from './themeState';
</script>

<style>
body {
  margin: 0;
  padding: 0;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif;
  transition: background-color 0.3s;
}

/* 亮色模式背景 */
body {
  background-color: #f5f5f5;
  color: #000000d9;
  --card-bg: #ffffff;
  --card-shadow: 0 4px 20px rgba(0,0,0,0.05);
  --border-color: rgba(0,0,0,0.06);
  --hover-bg: rgba(24, 144, 255, 0.06); /* 蓝色微光 */
  --text-color: inherit;
}

/* 暗色模式背景 */
body.dark-mode {
  background-color: #141414; /* Ant Design Dark bg */
  color: #ffffffd9;
  --card-bg: #1f1f1f;
  --card-shadow: 0 4px 20px rgba(0,0,0,0.2);
  --border-color: rgba(255,255,255,0.05);
  --hover-bg: rgba(24, 144, 255, 0.15); /* 蓝色微光 */
  --text-color: rgba(255, 255, 255, 0.85);
}

.app-dark {
  min-height: 100vh;
}
</style>
