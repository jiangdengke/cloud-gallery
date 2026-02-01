<template>
  <div class="main-container">
    <div class="header">
      <div class="brand">
        <cloud-server-outlined class="logo-icon" />
        <span class="title">Cloud Gallery 管理后台</span>
      </div>
      <div class="actions">
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
import { 
  CloudServerOutlined, 
  LogoutOutlined 
} from '@ant-design/icons-vue';
import FileExplorer from '../components/FileExplorer.vue';
import { setApiKey } from '../api/file';
import { useRouter } from 'vue-router';
import { message } from 'ant-design-vue';

const router = useRouter();

const handleLogout = () => {
  setApiKey(null);
  message.success('已退出管理模式');
  router.push('/');
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
  background: #001529; /* 深色 Header 区分前台 */
  box-shadow: 0 1px 4px rgba(0,0,0,0.1);
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
  color: #fff; /* 白色文字 */
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
