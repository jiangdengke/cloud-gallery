<template>
    <div class="main-container">
      <div class="header">
      <div class="brand" @click="goHome">
        <cloud-server-outlined class="logo-icon" />
        <span class="title">Cloud Gallery 管理后台</span>
      </div>
      <div class="actions">
        <a-button type="text" shape="circle" @click="toggleTheme">
          <template #icon>
            <bulb-outlined v-if="!isDark" />
            <bulb-filled v-else style="color: #fadb14" />
          </template>
        </a-button>
        <a-button danger ghost @click="handleLogout">
          <template #icon><logout-outlined /></template>
          退出管理
        </a-button>
      </div>
    </div>

    <div class="content">
      <FileExplorer :isAdmin="true" />
    </div>
  </div>
</template>

<script setup>
// 管理后台（前端业务代码）
// - 依赖本地保存的 api_key（见 web/src/api/file.js）
// - 退出时清空 api_key 并返回首页

import { 
  CloudServerOutlined, 
  LogoutOutlined,
  BulbOutlined,
  BulbFilled
} from '@ant-design/icons-vue';
import FileExplorer from '../components/FileExplorer.vue';
import { setApiKey } from '../api/file';
import { useRouter } from 'vue-router';
import { message } from 'ant-design-vue';
import { isDark, toggleTheme } from '../themeState';

const router = useRouter();

const goHome = () => router.push('/');

const handleLogout = () => {
  // 清理管理员 Key，回到游客模式
  setApiKey(null);
  message.success('已退出管理模式');
  router.push('/');
};
</script>

<style scoped>
.main-container {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}
.header {
  height: 64px;
  background: transparent;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 24px;
  z-index: 10;
}
.brand {
  display: flex;
  align-items: center;
  font-size: 18px;
  font-weight: 600;
  color: inherit;
  cursor: pointer;
}
.logo-icon {
  font-size: 24px;
  margin-right: 10px;
}
.content {
  flex: 1;
  padding: 0 24px 24px 24px;
  max-width: 1400px;
  width: 100%;
  margin: 0 auto;
}
.actions {
  display: flex;
  gap: 12px;
  align-items: center;
}
</style>
