<template>
  <div class="explorer-container" @click="closeContextMenu">
    <!-- 视图 A：文件列表模式 -->
    <template v-if="!previewFile">
      <!-- 管理工具栏 -->
      <div class="toolbar" v-if="isAdmin">
        <div class="toolbar-left">
          <a-space>
            <a-upload
              :show-upload-list="false"
              :customRequest="handleUpload"
              :disabled="uploading"
              multiple
            >
              <a-button type="primary" :loading="uploading">
                <template #icon><upload-outlined /></template>
                上传文件
              </a-button>
            </a-upload>

            <a-button @click="openCreateFolder">
              <template #icon><folder-add-outlined /></template>
              新建文件夹
            </a-button>

            <a-button :disabled="!canDownloadCurrentFolder" @click="downloadCurrentFolder">
              <template #icon><download-outlined /></template>
              下载当前文件夹
            </a-button>

            <a-button @click="refresh">
              <template #icon><reload-outlined /></template>
              刷新
            </a-button>
          </a-space>
        </div>
        <div class="toolbar-right">
          <a-input
            v-model:value="searchKeyword"
            allowClear
            placeholder="搜索当前目录"
            style="width: 240px;"
          >
            <template #prefix><search-outlined /></template>
          </a-input>
        </div>
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
          :dataSource="displayFiles"
          :columns="columns"
          :loading="loading"
          rowKey="id"
          :pagination="false"
          :customRow="customRow"
        >
          <!-- 列表内容渲染 -->
          <template #bodyCell="{ column, record }">
            <!-- 名称列 (包含图标) -->
            <template v-if="column.key === 'name'">
              <div class="name-cell">
                <folder-filled v-if="record.is_folder" class="file-icon folder" />
                <file-image-outlined v-else-if="isImage(record)" class="file-icon image" />
                <file-outlined v-else class="file-icon file" />
                <span class="file-name">{{ record.name }}</span>
                <a-tag v-if="record.is_public === false" color="orange" class="meta-tag">
                  <lock-outlined /> 私有
                </a-tag>
              </div>
            </template>

            <!-- 大小列 -->
            <template v-else-if="column.key === 'size'">
              <span v-if="!record.is_folder" class="size-text">{{ formatSize(record.size) }}</span>
              <span v-else>-</span>
            </template>

             <!-- 时间列 -->
             <template v-else-if="column.key === 'updated_at'">
              <span class="date-text">{{ formatDate(record.updated_at) }}</span>
            </template>

            <template v-else-if="column.key === 'actions'">
              <a-dropdown trigger="click" placement="bottomRight">
                <a-button type="text" class="action-btn" @click.stop>
                  <more-outlined />
                </a-button>
                <template #overlay>
                  <a-menu @click="({ key }) => handleRowAction(key, record)">
                    <a-menu-item key="download">
                      <download-outlined /> 下载
                    </a-menu-item>
                    <a-menu-item key="share">
                      <share-alt-outlined /> 分享
                    </a-menu-item>
                    <a-menu-item key="access">
                      <lock-outlined /> 访问设置
                    </a-menu-item>
                    <a-menu-item key="rename">
                      <edit-outlined /> 重命名
                    </a-menu-item>
                    <a-menu-item key="move">
                      <scissor-outlined /> 移动
                    </a-menu-item>
                    <a-menu-divider />
                    <a-menu-item key="delete">
                      <delete-outlined style="color: #ff4d4f;" />
                      <span style="color: #ff4d4f;">删除</span>
                    </a-menu-item>
                  </a-menu>
                </template>
              </a-dropdown>
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
            下载原图
          </a-button>
        </div>
        <div class="preview-body">
          <img :src="previewSrc" class="preview-img" alt="preview" />
        </div>
      </div>
    </template>
    
    <!-- 右键菜单 -->
    <div 
      v-if="contextMenu.visible" 
      class="context-menu" 
      :style="{ left: contextMenu.x + 'px', top: contextMenu.y + 'px' }"
      @click.stop
    >
      <div class="menu-item" @click="handleMenuDownload">
        <download-outlined /> 下载
      </div>
      <!-- 仅 Admin 模式显示的菜单项 -->
      <template v-if="isAdmin">
        <div class="menu-item" @click="handleMenuRename">
          <edit-outlined /> 重命名
        </div>
        <div class="menu-item" @click="handleMenuMove">
          <scissor-outlined /> 移动
        </div>
        <div class="menu-item" @click="handleMenuShare">
          <share-alt-outlined /> 分享
        </div>
        <div class="menu-item" @click="handleMenuAccess">
          <lock-outlined /> 访问设置
        </div>
        <div class="menu-item delete" @click="handleMenuDelete">
          <delete-outlined /> 删除
        </div>
      </template>
    </div>

    <!-- 弹窗组件 (Admin 模式) -->
    <template v-if="isAdmin">
      <a-modal v-model:open="showCreateFolderModal" title="新建文件夹" @ok="handleCreateFolder">
        <a-input v-model:value="newFolderName" placeholder="文件夹名称" @pressEnter="handleCreateFolder" />
      </a-modal>

      <a-modal v-model:open="showRenameModal" title="重命名" @ok="handleRename">
        <a-input v-model:value="renameInput" placeholder="新名称" @pressEnter="handleRename" />
      </a-modal>

      <a-modal v-model:open="showMoveModal" title="移动到" @ok="handleMove">
        <div v-if="treeLoading" style="text-align:center; padding: 20px;">加载中...</div>
        <a-tree
          v-else
          v-model:selectedKeys="moveTargetId"
          :tree-data="treeData"
          :load-data="onLoadTreeData"
          block-node
          default-expand-all
        >
          <template #title="{ title }">
            <folder-filled style="color: #ffca28; margin-right: 5px;" />
            {{ title }}
          </template>
        </a-tree>
      </a-modal>

      <a-modal
        v-model:open="showShareModal"
        :title="shareResult ? '分享链接' : '创建分享链接'"
        :okText="shareResult ? '关闭' : '创建'"
        :confirmLoading="shareCreating"
        @ok="handleShareOk"
      >
        <template v-if="!shareResult">
          <div style="margin-bottom: 12px;">
            将为 <strong>{{ shareTarget?.name }}</strong> 创建分享链接
          </div>
          <a-input
            v-model:value="sharePassword"
            placeholder="提取码（可选，6 位数字）"
            style="margin-bottom: 12px;"
            :maxlength="6"
            inputmode="numeric"
          />
          <a-date-picker
            v-model:value="shareExpiredAt"
            show-time
            style="width: 100%;"
            placeholder="过期时间（可选）"
          />
        </template>
        <template v-else>
          <a-input-group compact>
            <a-input :value="shareResult.link" readonly style="width: calc(100% - 88px);" />
            <a-button type="primary" @click="copyShareLink">复制</a-button>
          </a-input-group>
          <div v-if="shareResult.expired_at" style="margin-top: 12px; opacity: 0.75;">
            过期时间：{{ shareResult.expired_at }}
          </div>
        </template>
      </a-modal>

      <a-modal
        v-model:open="showAccessModal"
        :title="`访问设置${accessTarget?.name ? ' - ' + accessTarget.name : ''}`"
        :confirmLoading="accessSaving"
        @ok="handleAccessSave"
        @cancel="cancelAccessModal"
      >
        <a-form layout="vertical">
          <a-form-item label="访问权限">
            <a-switch v-model:checked="accessIsPublic" checked-children="公开" un-checked-children="私有" />
          </a-form-item>

          <template v-if="accessIsPublic">
            <a-alert type="success" show-icon message="公开内容无需 Key，即可访问/下载" />
          </template>
          <template v-else>
            <a-form-item label="Key（6 位数字）">
              <a-input-password
                v-model:value="accessPasswordInput"
                placeholder="留空=不修改；设置/重置 Key"
                :maxlength="6"
                inputmode="numeric"
              />
            </a-form-item>
            <a-alert type="info" show-icon message="私有内容仍会出现在列表中，但访问/下载需要 Key" />
          </template>
        </a-form>
      </a-modal>
    </template>

    <a-modal
      v-model:open="showUnlockModal"
      title="需要 Key"
      @ok="submitUnlockPassword"
      @cancel="cancelUnlockModal"
    >
      <a-input-password
        v-model:value="unlockPasswordInput"
        placeholder="Key（6 位数字）"
        :maxlength="6"
        inputmode="numeric"
        @pressEnter="submitUnlockPassword"
      />
    </a-modal>
  </div>
</template>

<script setup>
// 文件浏览器组件（前端业务代码）
// - isAdmin=false：游客浏览模式（只读 + 私有 Key 解锁）
// - isAdmin=true：管理模式（上传/移动/删除/访问设置/创建分享）

import { ref, onMounted, computed } from 'vue';
import { useFileExplorer } from '../composables/useFileExplorer';
import { message } from 'ant-design-vue';
import { 
  FolderFilled, 
  FileOutlined,
  FolderAddOutlined,
  UploadOutlined,
  DeleteOutlined,
  EditOutlined,
  FileImageOutlined,
  ScissorOutlined,
  DownloadOutlined,
  ArrowLeftOutlined,
  ShareAltOutlined,
  LockOutlined,
  ReloadOutlined,
  SearchOutlined,
  MoreOutlined
} from '@ant-design/icons-vue';
import 'github-markdown-css/github-markdown.css';

const props = defineProps({
  isAdmin: {
    type: Boolean,
    default: false
  }
});

// --- 使用 Composable（大部分业务逻辑在 useFileExplorer 内） ---
const {
  loading, files, breadcrumbs, readmeContent,
  showCreateFolderModal, newFolderName,
  showRenameModal, renameInput,
  showMoveModal, moveTargetId, treeData, treeLoading,
  showShareModal, shareTarget, sharePassword, shareExpiredAt, shareCreating, shareResult,
  uploading,
  showUnlockModal, unlockPasswordInput, submitUnlockPassword, cancelUnlockModal,
  showAccessModal, accessTarget, accessIsPublic, accessPasswordInput, accessSaving,
  openAccessSettings, handleAccessSave, cancelAccessModal,
  fetchFiles, openCreateFolder, handleCreateFolder,
  openRename, handleRename, handleDelete,
  openMove, handleMove, onLoadTreeData,
  handleUpload, handleBreadcrumbClick,
  openShare, handleShareOk, copyShareLink,
  formatSize, formatDate, isImage, downloadFile,
  previewFile, previewSrc, closePreview,
  handleItemClick
} = useFileExplorer({ isAdmin: props.isAdmin });

// --- UI 独有状态 ---
// 右键菜单（仅前端交互，不依赖后端）
const contextMenu = ref({ visible: false, x: 0, y: 0, record: null });
// 当前目录内搜索关键词（仅前端过滤）
const searchKeyword = ref('');

// 初始化
onMounted(() => {
  // 首次进入默认拉取根目录列表
  fetchFiles();
});

// 表格列定义
const columns = computed(() => {
  const baseColumns = [
    { title: '名称', key: 'name', dataIndex: 'name' }, // 合并图标到这一列
    { title: '大小', key: 'size', dataIndex: 'size', width: 120, align: 'right' },
    { title: '修改时间', key: 'updated_at', dataIndex: 'updated_at', width: 180, align: 'right' },
  ];

  if (props.isAdmin) {
    baseColumns.push({ title: '', key: 'actions', width: 64, align: 'right' });
  }

  return baseColumns;
});

const displayFiles = computed(() => {
  const keyword = (searchKeyword.value || '').toString().trim().toLowerCase();
  if (!keyword) return files.value;

  // 在当前目录内做简单模糊匹配
  return files.value.filter((file) => (file?.name || '').toString().toLowerCase().includes(keyword));
});

const currentBreadcrumb = computed(() => breadcrumbs.value[breadcrumbs.value.length - 1] ?? null);

const canDownloadCurrentFolder = computed(() => {
  return currentBreadcrumb.value && currentBreadcrumb.value.id !== null && currentBreadcrumb.value.id !== undefined;
});

const downloadCurrentFolder = async () => {
  // “下载当前文件夹”本质上是触发对当前目录节点的下载（后端会打包 zip）
  const current = currentBreadcrumb.value;
  if (!current || current.id === null || current.id === undefined) {
    message.warning('根目录不支持打包下载');
    return;
  }

  await downloadFile({
    id: current.id,
    name: current.name || 'folder',
    is_folder: true,
    is_protected: false,
    is_public: true,
  });
};

const refresh = () => {
  // 刷新当前目录
  const id = currentBreadcrumb.value?.id ?? null;
  fetchFiles(id);
};

const handleRowAction = async (action, record) => {
  if (!record) return;

  switch (action) {
    case 'download':
      await downloadFile(record);
      return;
    case 'share':
      openShare(record);
      return;
    case 'access':
      openAccessSettings(record);
      return;
    case 'rename':
      openRename(record);
      return;
    case 'move':
      openMove(record);
      return;
    case 'delete':
      handleDelete(record);
      return;
    default:
      return;
  }
};

// 点击行处理
const handleRowClick = (record) => {
  closeContextMenu();
  handleItemClick(record);
};

// 自定义行事件
const customRow = (record) => {
  return {
    onClick: () => handleRowClick(record),
    onContextmenu: (e) => {
      e.preventDefault();
      // 在鼠标位置显示右键菜单
      contextMenu.value = {
        visible: true,
        x: e.clientX,
        y: e.clientY,
        record: record
      };
    }
  };
};

const closeContextMenu = () => {
  contextMenu.value.visible = false;
};

// 菜单点击代理
const handleMenuDownload = () => {
  if (contextMenu.value.record) downloadFile(contextMenu.value.record);
  closeContextMenu();
};

const handleMenuRename = () => {
  if (contextMenu.value.record) openRename(contextMenu.value.record);
  closeContextMenu();
};

const handleMenuMove = () => {
  if (contextMenu.value.record) openMove(contextMenu.value.record);
  closeContextMenu();
};

const handleMenuDelete = () => {
  if (contextMenu.value.record) handleDelete(contextMenu.value.record);
  closeContextMenu();
};

const handleMenuShare = () => {
  if (contextMenu.value.record) openShare(contextMenu.value.record);
  closeContextMenu();
};

const handleMenuAccess = () => {
  if (contextMenu.value.record) openAccessSettings(contextMenu.value.record);
  closeContextMenu();
};
</script>

<style scoped>
.toolbar {
  margin-bottom: 24px;
  display: flex;
  gap: 16px;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
}
.toolbar-left,
.toolbar-right {
  display: flex;
  align-items: center;
}

.action-btn {
  padding: 0 8px;
}
.breadcrumb {
  margin-bottom: 24px;
  font-size: 16px;
  padding-left: 4px;
}

/* --- 核心调整：使用 CSS 变量 --- */
.file-card {
  background: var(--card-bg) !important;
  border-radius: 12px;
  box-shadow: var(--card-shadow) !important;
  min-height: 400px;
  border: 1px solid var(--border-color);
  overflow: hidden;
}

.file-card :deep(.ant-card-body) {
  padding: 0;
}

.file-name {
  font-weight: 500;
  font-size: 16px; /* 增大字体 */
  color: var(--text-color);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.name-cell {
  display: flex;
  align-items: center;
  gap: 16px; /* 增大间距 */
}

.meta-tag {
  margin-left: 4px;
}

.file-icon {
  font-size: 22px; /* 增大图标 */
  display: flex;
  align-items: center;
}
.file-icon.folder { color: #ffca28; font-size: 24px; }
.file-icon.image { color: #36cfc9; }
.file-icon.file { color: rgba(0,0,0,0.4); }
:global(.dark-mode) .file-icon.file { color: rgba(255,255,255,0.4); }

.size-text, .date-text {
  font-size: 14px;
  color: var(--text-color);
  opacity: 0.45;
}

/* 表格样式重置 */
:deep(.ant-table) {
  background: transparent !important;
  color: var(--text-color);
}
:deep(.ant-table-container),
:deep(.ant-table-content) {
  border: none !important;
}
:deep(table) {
  border-collapse: collapse !important;
  border-spacing: 0 !important;
  border: none !important;
}

/* 表头 */
:deep(.ant-table-thead > tr > th) {
  background: transparent !important;
  border: none !important;
  border-bottom: none !important;
  color: var(--text-color);
  opacity: 0.35;
  font-weight: 500;
  padding: 16px 24px; /* 增大表头高度 */
  font-size: 14px;
}

/* 每一行 */
:deep(.ant-table-tbody > tr) {
  border: none !important;
}
:deep(.ant-table-tbody > tr > td) {
  background: transparent !important;
  border: none !important;
  border-bottom: none !important;
  padding: 16px 24px !important; /* 增大行高 */
  transition: all 0.2s;
  color: var(--text-color);
}

/* 核武器：清除所有伪元素线条 */
:deep(.ant-table-thead > tr > th::before),
:deep(.ant-table-thead > tr > th::after),
:deep(.ant-table-tbody > tr > td::before),
:deep(.ant-table-tbody > tr > td::after) {
  display: none !important;
  content: none !important;
}

/* 悬停效果 */
:deep(.ant-table-tbody > tr:hover > td) {
  background: var(--hover-bg) !important;
}
:deep(.ant-table-tbody > tr:hover > td:first-child) {
  box-shadow: inset 4px 0 0 #1890ff !important;
}

:deep(.ant-table-row) {
  cursor: pointer;
  user-select: none;
}

/* README 卡片 */
.readme-card {
  margin-top: 24px;
  background: var(--card-bg) !important;
  border-radius: 12px;
  padding: 24px;
  box-shadow: var(--card-shadow) !important;
  color: var(--text-color);
}

/* 预览样式 */
.preview-container {
  background: var(--card-bg);
  border-radius: 12px;
  padding: 24px;
  box-shadow: var(--card-shadow);
  min-height: 400px;
}
.preview-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24px;
  border-bottom: 1px solid var(--border-color);
  padding-bottom: 16px;
}
.preview-title {
  font-size: 18px;
  font-weight: 600;
  color: var(--text-color);
}
.preview-body {
  display: flex;
  justify-content: center;
  background: rgba(0,0,0,0.02);
  padding: 40px;
  border-radius: 8px;
}
.preview-img {
  max-width: 100%;
  max-height: 80vh;
  box-shadow: 0 8px 24px rgba(0,0,0,0.1);
  border-radius: 4px;
}
:global(.dark-mode) .preview-body {
  background: rgba(255,255,255,0.02);
}

/* 右键菜单 */
.context-menu {
  position: fixed;
  background: var(--card-bg);
  border: 1px solid var(--border-color);
  box-shadow: 0 4px 16px rgba(0,0,0,0.2);
  border-radius: 8px;
  z-index: 1000;
  min-width: 140px;
  padding: 6px;
  color: var(--text-color);
}

.menu-item {
  padding: 10px 12px;
  cursor: pointer;
  border-radius: 6px; 
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 10px;
  color: inherit;
  font-size: 14px;
}

.menu-item:hover {
  background-color: var(--hover-bg);
  color: #1890ff;
}

.menu-item.delete {
  color: #ff4d4f;
}

.menu-item.delete:hover {
  background-color: rgba(255, 77, 79, 0.1);
}
</style>
