<template>
  <div class="explorer-container" @click="closeContextMenu">
    <!-- 管理工具栏 -->
    <div class="toolbar" v-if="isAdmin">
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
      </a-space>
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
        <!-- 图标列 -->
        <template #bodyCell="{ column, record }">
          <template v-if="column.key === 'icon'">
            <folder-filled v-if="record.is_folder" style="color: #ffca28; font-size: 24px;" />
            <file-image-outlined v-else-if="isImage(record)" style="color: #36cfc9; font-size: 20px;" />
            <file-outlined v-else style="color: #666; font-size: 20px;" />
          </template>
          
          <!-- 名称列 -->
          <template v-else-if="column.key === 'name'">
            <span class="file-name">{{ record.name }}</span>
          </template>

          <!-- 大小列 -->
          <template v-else-if="column.key === 'size'">
            <span v-if="!record.is_folder" style="color: #999">{{ formatSize(record.size) }}</span>
            <span v-else>-</span>
          </template>

           <!-- 时间列 -->
           <template v-else-if="column.key === 'updated_at'">
            <span style="color: #999">{{ formatDate(record.updated_at) }}</span>
          </template>
        </template>
      </a-table>
    </a-card>

    <!-- README 展示区 -->
    <a-card v-if="readmeContent" class="readme-card" :bordered="false" title="README.md">
      <div class="markdown-body" v-html="readmeContent"></div>
    </a-card>
    
    <!-- 图片预览组件 -->
    <a-image
      :style="{ display: 'none' }"
      :preview="{
        visible: previewVisible,
        onVisibleChange: (val) => (previewVisible = val),
        src: previewImage
      }"
    />
    
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
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useFileExplorer } from '../composables/useFileExplorer';
import { Modal } from 'ant-design-vue';
import { 
  FolderFilled, 
  FileOutlined,
  FolderAddOutlined,
  UploadOutlined,
  DeleteOutlined,
  EditOutlined,
  FileImageOutlined,
  ScissorOutlined,
  DownloadOutlined
} from '@ant-design/icons-vue';
import 'github-markdown-css/github-markdown.css';

const props = defineProps({
  isAdmin: {
    type: Boolean,
    default: false
  }
});

// --- 使用 Composable ---
const {
  loading, files, breadcrumbs, readmeContent,
  showCreateFolderModal, newFolderName,
  showRenameModal, renameInput,
  showMoveModal, moveTargetId, treeData, treeLoading,
  uploading,
  fetchFiles, openCreateFolder, handleCreateFolder,
  openRename, handleRename, handleDelete,
  openMove, handleMove, onLoadTreeData,
  handleUpload, handleBreadcrumbClick,
  formatSize, formatDate, isImage, downloadFile
} = useFileExplorer();

// --- UI 独有状态 (右键菜单 & 预览) ---
const contextMenu = ref({ visible: false, x: 0, y: 0, record: null });
const previewVisible = ref(false);
const previewImage = ref('');

// 初始化
onMounted(() => {
  fetchFiles();
});

// 表格列定义
const columns = computed(() => {
  return [
    { title: '', key: 'icon', width: 50, align: 'center' },
    { title: '名称', key: 'name', dataIndex: 'name' },
    { title: '大小', key: 'size', dataIndex: 'size', width: 120, align: 'right' },
    { title: '修改时间', key: 'updated_at', dataIndex: 'updated_at', width: 180, align: 'right' },
  ];
});

// 点击行处理
const handleRowClick = (record) => {
  closeContextMenu();
  if (record.is_folder) {
    breadcrumbs.value.push({ id: record.id, name: record.name });
    fetchFiles(record.id);
  } else if (isImage(record)) {
    previewImage.value = `/api/files/${record.id}/download`;
    previewVisible.value = true;
  } else {
    downloadFile(record);
  }
};

// 自定义行事件 (右键菜单)
const customRow = (record) => {
  return {
    onClick: () => handleRowClick(record),
    onContextmenu: (e) => {
      e.preventDefault();
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
</script>

<style scoped>
.toolbar {
  margin-bottom: 24px;
  display: flex;
  gap: 16px;
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
  font-size: 15px;
  color: var(--text-color);
  margin-left: 8px;
}

/* 表格样式重置 */
:deep(.ant-table) {
  background: transparent !important;
  color: var(--text-color);
}
:deep(table) {
  border-collapse: collapse !important;
  border-spacing: 0 !important;
}

/* 表头 */
:deep(.ant-table-thead > tr > th) {
  background: transparent !important; /* 表头透明或微调 */
  border-bottom: 1px solid var(--border-color) !important;
  color: var(--text-color);
  opacity: 0.6;
  font-weight: 500;
  padding: 12px 24px;
}

/* 每一行 */
:deep(.ant-table-tbody > tr > td) {
  background: transparent !important;
  border-bottom: 1px solid var(--border-color) !important;
  padding: 14px 24px !important;
  transition: all 0.2s;
  color: var(--text-color);
}
:deep(.ant-table-tbody > tr:last-child > td) {
  border-bottom: none !important;
}

/* 悬停效果 */
:deep(.ant-table-tbody > tr:hover > td) {
  background: var(--hover-bg) !important;
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

/* 右键菜单 */
.context-menu {
  position: fixed;
  background: var(--card-bg);
  border: 1px solid var(--border-color);
  box-shadow: 0 4px 16px rgba(0,0,0,0.2); /* 菜单阴影保持较深 */
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