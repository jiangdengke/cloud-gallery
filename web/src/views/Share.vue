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
        <a-button @click="goHome">返回首页</a-button>
      </div>
    </div>

    <div class="content">
      <a-spin :spinning="loading">
        <a-alert v-if="error" type="error" :message="error" show-icon />

        <template v-else-if="share && share.is_folder">
          <ShareExplorer
            :token="token"
            :password="sharePassword"
            :rootId="share.file_id"
            :rootName="share.name"
          />
        </template>

        <template v-else-if="share">
          <a-card :bordered="false" class="file-card">
            <div class="file-header">
              <div class="file-title">{{ share.name }}</div>
              <a-button type="primary" ghost @click="downloadRoot">
                <template #icon><download-outlined /></template>
                下载
              </a-button>
            </div>

            <div class="file-meta">
              <span v-if="share.size !== null">大小：{{ formatSize(share.size) }}</span>
              <span v-if="share.expired_at">过期时间：{{ share.expired_at }}</span>
            </div>

            <div v-if="isImageName(share.name)" class="preview-body">
              <img :src="rootDownloadUrl" class="preview-img" alt="preview" />
            </div>

            <div v-else-if="isMarkdownName(share.name)" class="markdown-body" v-html="markdownHtml"></div>
          </a-card>
        </template>
      </a-spin>
    </div>

    <a-modal v-model:open="showPasswordModal" title="请输入提取码" @ok="submitPassword">
      <a-input-password
        v-model:value="passwordInput"
        placeholder="6 位数字提取码"
        :maxlength="6"
        inputmode="numeric"
        @pressEnter="submitPassword"
      />
    </a-modal>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { message } from 'ant-design-vue';
import api from '../api/file';
import { getShareDetail, buildShareDownloadUrl } from '../api/share';
import ShareExplorer from '../components/ShareExplorer.vue';
import MarkdownIt from 'markdown-it';
import {
  CloudServerOutlined,
  BulbOutlined,
  BulbFilled,
  DownloadOutlined
} from '@ant-design/icons-vue';
import { isDark, toggleTheme } from '../themeState';

const route = useRoute();
const router = useRouter();

const token = computed(() => route.params.token);

const loading = ref(false);
const error = ref('');
const share = ref(null);

const showPasswordModal = ref(false);
const passwordInput = ref('');
const sharePassword = ref('');

const md = new MarkdownIt({ html: true, linkify: true, typographer: true });
const markdownHtml = ref('');

const rootDownloadUrl = computed(() => {
  if (!share.value) return '';
  return buildShareDownloadUrl(token.value, {
    fileId: share.value.file_id,
    password: sharePassword.value || null
  });
});

const goHome = () => router.push('/');

const isImageName = (name) => /\.(jpg|jpeg|png|gif|webp|bmp|svg)$/i.test(name || '');
const isMarkdownName = (name) => /\.md$/i.test(name || '');

const formatSize = (bytes) => {
  if (bytes === 0) return '0 B';
  const k = 1024;
  const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const loadMarkdown = async () => {
  markdownHtml.value = '';
  if (!share.value || share.value.is_folder || !isMarkdownName(share.value.name)) return;

  try {
    const headers = {};
    if (sharePassword.value) headers['X-Share-Password'] = sharePassword.value;

    const content = await api.get(`/shares/${token.value}/download`, {
      params: {
        file_id: share.value.file_id,
      },
      headers,
      responseType: 'text',
      transformResponse: [data => data]
    });

    if (typeof content === 'string') {
      markdownHtml.value = md.render(content);
    }
  } catch (err) {
    console.error(err);
  }
};

const loadShare = async () => {
  loading.value = true;
  error.value = '';
  share.value = null;
  markdownHtml.value = '';

  try {
    const res = await getShareDetail(token.value, sharePassword.value || null);
    if (res.code === 20000) {
      share.value = res.data;
      showPasswordModal.value = false;
      passwordInput.value = '';
      await loadMarkdown();
      return;
    }

    // 密码相关：提示并弹窗
    if (res.code === 30009 || res.code === 30010) {
      showPasswordModal.value = true;
      if (res.code === 30010) {
        message.error(res.message || '提取码错误');
      }
      return;
    }

    error.value = res.message || '分享链接不可用';
  } catch (err) {
    console.error(err);
    error.value = '网络请求失败';
  } finally {
    loading.value = false;
  }
};

const submitPassword = async () => {
  if (!passwordInput.value) return;
  sharePassword.value = passwordInput.value;
  await loadShare();
};

const downloadRoot = () => {
  if (!share.value) return;
  const link = document.createElement('a');
  link.href = rootDownloadUrl.value;
  link.setAttribute('download', share.value.is_folder ? `${share.value.name}.zip` : share.value.name);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
};

onMounted(() => loadShare());
watch(() => token.value, () => {
  sharePassword.value = '';
  passwordInput.value = '';
  loadShare();
});
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

.file-card {
  background: var(--card-bg) !important;
  border-radius: 12px;
  box-shadow: var(--card-shadow) !important;
  border: 1px solid var(--border-color);
  overflow: hidden;
}

.file-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  margin-bottom: 12px;
}

.file-title {
  font-size: 18px;
  font-weight: 600;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.file-meta {
  display: flex;
  gap: 18px;
  opacity: 0.75;
  margin-bottom: 12px;
  flex-wrap: wrap;
}

.preview-body {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 12px 0;
}

.preview-img {
  max-width: 100%;
  border-radius: 8px;
}
</style>
