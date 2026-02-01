<template>
  <div class="main-container">
    <div class="header">
      <div class="brand">
        <cloud-server-outlined class="logo-icon" />
        <span class="title">Cloud Gallery</span>
      </div>
      <div class="actions">
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
import { ref } from 'vue';
import { 
  CloudServerOutlined, 
  LoginOutlined 
} from '@ant-design/icons-vue';
import FileExplorer from '../components/FileExplorer.vue';
import { setApiKey } from '../api/file';
import { useRouter } from 'vue-router';
import { message } from 'ant-design-vue';

const router = useRouter();
const showLoginModal = ref(false);
const apiKeyInput = ref('');

const handleLogin = () => {
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
  background-color: #f0f2f5;
  display: flex;
  flex-direction: column;
}
.header {
  height: 64px;
  background: #fff;
  box-shadow: 0 1px 4px rgba(0,0,0,0.05);
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
  color: #1890ff;
}
.logo-icon {
  font-size: 24px;
  margin-right: 10px;
}
.content {
  flex: 1;
  padding: 24px;
  max-width: 1200px;
  width: 100%;
  margin: 0 auto;
}
</style>
