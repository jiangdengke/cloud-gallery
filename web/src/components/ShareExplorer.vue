<template>
  <div class="explorer-container">
    <!-- 视图 A：文件列表模式 -->
    <template v-if="!previewFile">
      <div class="toolbar">
        <a-button type="primary" ghost @click="downloadCurrentFolder">
          <template #icon><download-outlined /></template>
          下载此文件夹
        </a-button>
      </div>

      <!-- 面包屑 -->
      <a-breadcrumb class="breadcrumb">
        <a-breadcrumb-item v-for="(item, index) in breadcrumbs" :key="item.id || 'root'">
          <a @click="handleBreadcrumbClick(item, index)">{{ item.name }}</a>
        </a-breadcrumb-item>
      </a-breadcrumb>

      <!-- 文件列表 -->
      <a-card :bordered="false" class="file-card">
        <a-table
          :dataSource="files"
          :columns="columns"
          :loading="loading"
          rowKey="id"
          :pagination="false"
          :customRow="customRow"
        >
          <template #bodyCell="{ column, record }">
            <template v-if="column.key === 'name'">
              <div class="name-cell">
                <folder-filled v-if="record.is_folder" class="file-icon folder" />
                <file-image-outlined v-else-if="isImage(record)" class="file-icon image" />
                <file-outlined v-else class="file-icon file" />
                <span class="file-name">{{ record.name }}</span>
              </div>
            </template>

            <template v-else-if="column.key === 'size'">
              <span v-if="!record.is_folder" class="size-text">{{ formatSize(record.size) }}</span>
              <span v-else>-</span>
            </template>

            <template v-else-if="column.key === 'updated_at'">
              <span class="date-text">{{ formatDate(record.updated_at) }}</span>
            </template>
          </template>
        </a-table>
      </a-card>

      <!-- README 展示区 -->
      <a-card v-if="readmeContent" class="readme-card" :bordered="false" title="README.md">
        <div class="markdown-body" v-html="readmeContent"></div>
      </a-card>
    </template>

    <!-- 视图 B：图片预览模式 -->
    <template v-else>
      <div class="preview-container">
        <div class="preview-header">
          <a-button type="text" @click="closePreview" class="back-btn">
            <template #icon><arrow-left-outlined /></template>
            返回列表
          </a-button>
          <span class="preview-title">{{ previewFile.name }}</span>
          <a-button type="primary" ghost @click="downloadFile(previewFile)">
            <template #icon><download-outlined /></template>
            下载
          </a-button>
        </div>
        <div class="preview-body">
          <img :src="previewSrc" class="preview-img" alt="preview" />
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
// 分享浏览器组件（前端业务代码）
// - 用于分享页面中展示“被分享的文件夹”并支持目录导航/下载/图片预览
// - 由于浏览器下载链接无法带自定义 Header，下载链接可能会拼接 ?password=

import { ref, onMounted, computed, watch } from 'vue';
import { message } from 'ant-design-vue';
import api from '../api/file';
import { getShareFiles, buildShareDownloadUrl } from '../api/share';
import MarkdownIt from 'markdown-it';
import {
  FolderFilled,
  FileOutlined,
  FileImageOutlined,
  DownloadOutlined,
  ArrowLeftOutlined
} from '@ant-design/icons-vue';
import 'github-markdown-css/github-markdown.css';

const props = defineProps({
  // 分享 token（路由参数 /s/:token）
  token: {
    type: String,
    required: true
  },
  // 分享提取码（6 位数字，可为空）
  password: {
    type: String,
    default: ''
  },
  // 分享根目录 id/名称（由 Share.vue 的详情接口传入）
  rootId: {
    type: [Number, String],
    required: true
  },
  rootName: {
    type: String,
    required: true
  }
});

// Markdown 渲染器：用于展示 README.md
const md = new MarkdownIt({ html: true, linkify: true, typographer: true });

const loading = ref(false);
const files = ref([]);
const breadcrumbs = ref([{ id: props.rootId, name: props.rootName }]);
const readmeContent = ref('');
const previewFile = ref(null);

const columns = computed(() => [
  { title: '名称', key: 'name', dataIndex: 'name' },
  { title: '大小', key: 'size', dataIndex: 'size', width: 120, align: 'right' },
  { title: '修改时间', key: 'updated_at', dataIndex: 'updated_at', width: 180, align: 'right' },
]);

const currentParentId = computed(() => {
  const last = breadcrumbs.value[breadcrumbs.value.length - 1];
  return last?.id;
});

const fetchFiles = async (parentId) => {
  loading.value = true;
  readmeContent.value = '';
  try {
    // 获取当前目录的子项列表（后端会校验 token/过期/提取码）
    const res = await getShareFiles(props.token, parentId, props.password);
    if (res.code === 20000) {
      files.value = res.data.list;
      // 自动加载 README.md（如果存在）
      const readme = files.value.find(f => !f.is_folder && f.name?.toLowerCase() === 'readme.md');
      if (readme) {
        loadReadme(readme.id);
      }
    } else {
      message.error(res.message || '加载失败');
    }
  } catch (err) {
    console.error(err);
    message.error('网络请求失败');
  } finally {
    loading.value = false;
  }
};

const loadReadme = async (id) => {
  try {
    const headers = {};
    if (props.password) headers['X-Share-Password'] = props.password;

    // README 通过下载接口获取原始文本，关闭 axios 的 JSON 解析
    const content = await api.get(`/shares/${props.token}/download`, {
      params: {
        file_id: id,
      },
      headers,
      responseType: 'text',
      transformResponse: [data => data]
    });

    if (typeof content !== 'string') return;

    // 兼容：如果后端返回 JSON 错误（文本形式），这里尝试解析并提示
    const trimmed = content.trim();
    if (trimmed.startsWith('{') && trimmed.endsWith('}')) {
      try {
        const json = JSON.parse(trimmed);
        if (json?.code && json?.message) {
          message.error(json.message);
          return;
        }
      } catch (e) {}
    }

    readmeContent.value = md.render(content);
  } catch (err) {
    console.error('README 加载失败', err);
  }
};

const openPreview = (record) => {
  previewFile.value = record;
};

const closePreview = () => {
  previewFile.value = null;
};

const previewSrc = computed(() => {
  if (!previewFile.value) return '';
  // 图片预览直接使用下载链接（浏览器会发起 GET /shares/{token}/download）
  return buildShareDownloadUrl(props.token, {
    fileId: previewFile.value.id,
    password: props.password || null
  });
});

const formatSize = (bytes) => {
  if (bytes === 0) return '0 B';
  const k = 1024;
  const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const formatDate = (dateStr) => {
  if (!dateStr) return '';
  return new Date(dateStr).toLocaleString();
};

const isImage = (record) => {
  const mime = record.mime_type || '';
  return mime.startsWith('image/') || /\.(jpg|jpeg|png|gif|webp|bmp|svg)$/i.test(record.name);
};

const downloadFile = (record) => {
  // 直接构建下载链接并触发 <a> 点击
  const url = buildShareDownloadUrl(props.token, {
    fileId: record.id,
    password: props.password || null
  });
  const link = document.createElement('a');
  link.href = url;
  link.setAttribute('download', record.is_folder ? `${record.name}.zip` : record.name);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
};

const downloadCurrentFolder = () => {
  // 下载当前面包屑所在目录（通常为 zip）
  const current = breadcrumbs.value[breadcrumbs.value.length - 1];
  if (!current) return;
  const url = buildShareDownloadUrl(props.token, {
    fileId: current.id,
    password: props.password || null
  });
  const link = document.createElement('a');
  link.href = url;
  link.setAttribute('download', `${current.name}.zip`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
};

const handleRowClick = (record) => {
  if (record.is_folder) {
    breadcrumbs.value.push({ id: record.id, name: record.name });
    fetchFiles(record.id);
  } else if (isImage(record)) {
    openPreview(record);
  } else {
    downloadFile(record);
  }
};

const customRow = (record) => ({
  onClick: () => handleRowClick(record)
});

const handleBreadcrumbClick = (item, index) => {
  if (previewFile.value) closePreview();
  if (index === breadcrumbs.value.length - 1) return;
  breadcrumbs.value = breadcrumbs.value.slice(0, index + 1);
  fetchFiles(item.id);
};

const reset = () => {
  previewFile.value = null;
  breadcrumbs.value = [{ id: props.rootId, name: props.rootName }];
  fetchFiles(props.rootId);
};

onMounted(() => {
  fetchFiles(currentParentId.value);
});

watch(() => props.token, () => reset());
watch(() => props.rootId, () => reset());
</script>

<style scoped>
.toolbar {
  margin-bottom: 16px;
  display: flex;
  justify-content: flex-end;
}

.breadcrumb {
  margin-bottom: 24px;
  font-size: 16px;
  padding-left: 4px;
}

.file-card {
  background: var(--card-bg) !important;
  border-radius: 12px;
  box-shadow: var(--card-shadow) !important;
  border: 1px solid var(--border-color);
  overflow: hidden;
}

.file-card :deep(.ant-card-body) {
  padding: 0;
}

.name-cell {
  display: flex;
  align-items: center;
  gap: 12px;
}

.file-icon {
  font-size: 20px;
}

.file-icon.folder {
  color: #ffca28;
}
.file-icon.image {
  color: #1890ff;
}
.file-icon.file {
  color: #8c8c8c;
}

.file-name {
  font-weight: 500;
}

.readme-card {
  margin-top: 24px;
  background: var(--card-bg) !important;
  border-radius: 12px;
  border: 1px solid var(--border-color);
  box-shadow: var(--card-shadow) !important;
}

.preview-container {
  display: flex;
  flex-direction: column;
  height: calc(100vh - 120px);
  background: var(--card-bg);
  border-radius: 12px;
  border: 1px solid var(--border-color);
  box-shadow: var(--card-shadow);
  overflow: hidden;
}

.preview-header {
  display: flex;
  align-items: center;
  padding: 12px 16px;
  border-bottom: 1px solid var(--border-color);
  gap: 12px;
}

.preview-title {
  flex: 1;
  font-weight: 600;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.preview-body {
  flex: 1;
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 24px;
}

.preview-img {
  max-width: 100%;
  max-height: 100%;
  border-radius: 8px;
}
</style>
