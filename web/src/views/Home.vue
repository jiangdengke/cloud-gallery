<template>
    <div class="main-container">
      <div class="header">
      <div class="brand" @click="goHome">
        <cloud-server-outlined class="logo-icon" />
        <span class="title">Cloud Gallery</span>
      </div>
      <div class="actions">
        <a-button type="text" shape="circle" @click="toggleTheme">
          <template #icon>
            <bulb-outlined v-if="!isDark" />
            <bulb-filled v-else style="color: #fadb14" />
          </template>
        </a-button>
        <a-button type="primary" ghost @click="showLoginModal = true">
          <template #icon><login-outlined /></template>
          管理登录
        </a-button>
      </div>
    </div>

    <div class="content">
      <FileExplorer :isAdmin="false" />
    </div>

    <!-- 登录弹窗 -->
    <a-modal v-model:open="showLoginModal" title="管理员登录" @ok="handleLogin">
      <a-input-password v-model:value="apiKeyInput" placeholder="请输入 API Key" @pressEnter="handleLogin" />
    </a-modal>
  </div>
</template>

<script setup>
// 首页（前端业务代码）
// - 默认游客模式浏览文件
// - 通过“管理登录”输入 API Key 后进入 /admin

import { ref } from 'vue';
import { 
  CloudServerOutlined, 
  LoginOutlined,
  BulbOutlined,
  BulbFilled
} from '@ant-design/icons-vue';
import FileExplorer from '../components/FileExplorer.vue';
import { setApiKey } from '../api/file';
import { useRouter } from 'vue-router';
import { message } from 'ant-design-vue';
import { isDark, toggleTheme } from '../themeState';

const router = useRouter();
const showLoginModal = ref(false);
const apiKeyInput = ref('');

const goHome = () => router.push('/');

const handleLogin = () => {
  // 简单校验：非空即可（后端会再校验 key 是否正确）
  if (!apiKeyInput.value) return;
  setApiKey(apiKeyInput.value);
  message.success('登录成功');
  showLoginModal.value = false;
  router.push('/admin');
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
  padding: 0 24px 24px 24px; /* 减小顶部间距 */
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
