<template>
  <div class="explorer-container" @click="closeContextMenu">
    <!-- 管理工具栏 (仅 Admin 模式显示) -->
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
import { ref, onMounted, computed, watch } from 'vue';
import { 
  getFiles, 
  createFolder, 
  deleteFiles, 
  renameFile,
  uploadFile,
  moveFile
} from '../api/file';
import api from '../api/file';
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
import { message, Modal } from 'ant-design-vue';
import MarkdownIt from 'markdown-it';
import 'github-markdown-css/github-markdown.css';

const props = defineProps({
  isAdmin: {
    type: Boolean,
    default: false
  }
});

const md = new MarkdownIt({ html: true, linkify: true, typographer: true });

// 状态
const loading = ref(false);
const files = ref([]);
const breadcrumbs = ref([
  { id: null, name: '全部文件' }
]);
const readmeContent = ref('');

// 管理操作状态
const showCreateFolderModal = ref(false);
const newFolderName = ref('');
const showRenameModal = ref(false);
const renameTarget = ref(null);
const renameInput = ref('');
const uploading = ref(false);
const activeUploads = ref(0);

// 移动文件状态
const showMoveModal = ref(false);
const moveTargetId = ref([]);
const movingFile = ref(null);
const treeData = ref([]);
const treeLoading = ref(false);

// 右键菜单与预览
const contextMenu = ref({ visible: false, x: 0, y: 0, record: null });
const previewVisible = ref(false);
const previewImage = ref('');

// 表格列定义
const columns = computed(() => {
  return [
    { title: '', key: 'icon', width: 50, align: 'center' },
    { title: '名称', key: 'name', dataIndex: 'name' },
    { title: '大小', key: 'size', dataIndex: 'size', width: 120, align: 'right' },
    { title: '修改时间', key: 'updated_at', dataIndex: 'updated_at', width: 180, align: 'right' },
  ];
});

// 当前父目录ID
const currentParentId = computed(() => {
  const last = breadcrumbs.value[breadcrumbs.value.length - 1];
  return last.id;
});

// 初始化
onMounted(() => {
  fetchFiles();
});

// 获取文件列表
const fetchFiles = async (parentId = null) => {
  loading.value = true;
  readmeContent.value = '';
  try {
    const res = await getFiles(parentId);
    if (res.code === 20000) {
      files.value = res.data.list;
      // 检查 README
      const readme = files.value.find(f => !f.is_folder && f.name.toLowerCase() === 'readme.md');
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
    const content = await api.get(`/files/${id}/download`, {
      responseType: 'text',
      transformResponse: [data => data]
    });
    if (typeof content === 'string') {
        readmeContent.value = md.render(content);
    }
  } catch (err) {
    console.error(err);
  }
};

const isImage = (record) => {
  const mime = record.mime_type || '';
  return mime.startsWith('image/') || /\.(jpg|jpeg|png|gif|webp|bmp|svg)$/i.test(record.name);
};

// 点击行
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

const downloadFile = (record) => {
  const url = `/api/files/${record.id}/download`;
  const link = document.createElement('a');
  link.href = url;
  link.setAttribute('download', record.name);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
};

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

// --- Admin Actions ---

const handleMenuDownload = () => {
  const record = contextMenu.value.record;
  closeContextMenu();
  if (record) downloadFile(record);
};

const handleMenuRename = () => {
  const record = contextMenu.value.record;
  closeContextMenu();
  if (record) openRename(record);
};

const handleMenuMove = () => {
  const record = contextMenu.value.record;
  closeContextMenu();
  if (record) openMove(record);
};

const handleMenuDelete = () => {
  const record = contextMenu.value.record;
  closeContextMenu();
  if (record) {
    Modal.confirm({
      title: '确认删除',
      content: `确定要删除 "${record.name}" 吗？此操作不可恢复。`,
      okText: '删除',
      okType: 'danger',
      cancelText: '取消',
      onOk: () => handleDelete(record)
    });
  }
};

const handleBreadcrumbClick = (item, index) => {
  if (index === breadcrumbs.value.length - 1) return;
  breadcrumbs.value = breadcrumbs.value.slice(0, index + 1);
  fetchFiles(item.id);
};

// 管理逻辑实现 (仅在 isAdmin 时调用有效，但代码可以保留在这里)
const openCreateFolder = () => {
  newFolderName.value = '';
  showCreateFolderModal.value = true;
};

const handleCreateFolder = async () => {
  if (!newFolderName.value) return;

  try {
    const res = await createFolder(newFolderName.value, currentParentId.value);
    console.log('Create Folder Response:', res); 
    console.log('Debug Check:', { 
      hasId: res?.id, 
      type: typeof res,
      isModel: res && res.id 
    });

    // 极度宽松的检查
    if ((res && res.id) || (res && res.status === 201) || (res && res.code === 201)) {
      message.success('创建成功');
      showCreateFolderModal.value = false;
      fetchFiles(currentParentId.value);
      return;
    }

    // 2. 如果返回了标准结构
    const isSuccess = 
      (res.code >= 20000 && res.code < 30000) || 
      (res.status >= 200 && res.status < 300) ||
      (res.status === 'success') || // 适配 status string
      (res.data && !res.error); 

    if (isSuccess) {
      message.success('创建成功');
      showCreateFolderModal.value = false;
      fetchFiles(currentParentId.value);
    } else {
      message.error(res.message || '创建失败');
    }
  } catch (err) {
    console.error(err);
    message.error('创建失败');
  }
};

const openRename = (record) => {
  renameTarget.value = record;
  renameInput.value = record.name;
  showRenameModal.value = true;
};

const handleRename = async () => {
  if (!renameInput.value || !renameTarget.value) return;
  try {
    const res = await renameFile(renameTarget.value.id, renameInput.value);
    if (res.code === 20000) {
      message.success('重命名成功');
      showRenameModal.value = false;
      fetchFiles(currentParentId.value);
    } else {
      message.error(res.message || '重命名失败');
    }
  } catch (err) {
    message.error('操作失败');
  }
};

const handleDelete = async (record) => {
  try {
    const res = await deleteFiles([record.id]);
    if (res.code === 20000) {
      message.success('删除成功');
      fetchFiles(currentParentId.value);
    } else {
      message.error(res.message || '删除失败');
    }
  } catch (err) {
    message.error('操作失败');
  }
};

const handleUpload = async ({ file, onSuccess, onError }) => {
  const formData = new FormData();
  formData.append('file', file);
  if (currentParentId.value) {
    formData.append('parent_id', currentParentId.value);
  }

  activeUploads.value++;
  uploading.value = true;
  
  try {
    const res = await uploadFile(formData);
    const isSuccess = 
      (res.code >= 20000 && res.code < 30000) || 
      (res.status >= 200 && res.status < 300) ||
      (res.message === '操作成功') ||
      (res.data && !res.error);

    if (isSuccess) {
      message.success(`${file.name} 上传成功`);
      onSuccess(res.data);
    } else {
      message.error(`${file.name}: ${res.message || '上传失败'}`);
      onError(new Error(res.message));
    }
  } catch (err) {
    message.error(`${file.name}: 上传出错`);
    onError(err);
  } finally {
    activeUploads.value--;
    if (activeUploads.value === 0) {
      uploading.value = false;
      setTimeout(() => fetchFiles(currentParentId.value), 500);
    }
  }
};

// 移动逻辑
const openMove = async (record) => {
  movingFile.value = record;
  showMoveModal.value = true;
  moveTargetId.value = [];
  treeData.value = [];
  treeLoading.value = true;
  try {
    const res = await getFiles(null);
    if (res.code === 20000) {
      treeData.value = [{
          title: '根目录', key: 'root', id: null,
          children: transformToTreeNodes(res.data.list)
      }];
    }
  } finally {
    treeLoading.value = false;
  }
};

const transformToTreeNodes = (list) => {
  return list.filter(item => item.is_folder).map(item => ({
      title: item.name, key: item.id, id: item.id, isLeaf: false
  }));
};

const onLoadTreeData = async (treeNode) => {
  if (treeNode.children && treeNode.children.length > 0) return;
  const parentId = treeNode.id;
  try {
    const res = await getFiles(parentId);
    if (res.code === 20000) {
      treeNode.dataRef.children = transformToTreeNodes(res.data.list);
      treeData.value = [...treeData.value]; 
    }
  } catch (err) {}
};

const handleMove = async () => {
  if (!movingFile.value) return;
  let targetId = null;
  if (Array.isArray(moveTargetId.value) && moveTargetId.value.length > 0) {
    targetId = moveTargetId.value[0];
  }
  if (targetId === 'root') targetId = null;
  if (moveTargetId.value.length === 0) {
    message.warning('请选择目标文件夹');
    return;
  }
  if (targetId == movingFile.value.parent_id) {
     message.warning('文件已在此目录下');
     return;
  }

  try {
    const res = await moveFile(movingFile.value.id, targetId);
    if (res.code === 20000) {
      message.success('移动成功');
      showMoveModal.value = false;
      fetchFiles(currentParentId.value);
    } else {
      message.error(res.message || '移动失败');
    }
  } catch (err) {
    message.error('操作失败');
  }
};

const formatSize = (bytes) => {
  if (bytes === 0) return '0 B';
  const k = 1024;
  const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const formatDate = (dateStr) => {
  if (!dateStr) return '';
  const date = new Date(dateStr);
  return date.toLocaleString();
};

</script>

<style scoped>
/* 这里复用之前的 CSS */
.toolbar {
  margin-bottom: 16px;
  display: flex;
  gap: 10px;
}
.breadcrumb {
  margin-bottom: 16px;
  font-size: 16px;
}
.file-card {
  border-radius: 8px;
  box-shadow: 0 1px 2px rgba(0,0,0,0.03);
  min-height: 400px;
}
.file-name {
  font-weight: 500;
  color: #333;
}
:deep(.ant-table-row) {
  cursor: pointer;
  user-select: none;
}
.readme-card {
  margin-top: 24px;
  border-radius: 8px;
  box-shadow: 0 1px 2px rgba(0,0,0,0.03);
}
.context-menu {
  position: fixed;
  background: #fff;
  border: 1px solid #eee;
  box-shadow: 0 2px 8px rgba(0,0,0,0.15);
  border-radius: 4px;
  z-index: 1000;
  min-width: 120px;
  padding: 4px 0;
}
.menu-item {
  padding: 8px 16px;
  cursor: pointer;
  transition: all 0.3s;
  display: flex;
  align-items: center;
  gap: 8px;
  color: #333;
}
.menu-item:hover {
  background-color: #f0f7ff;
  color: #1890ff;
}
.menu-item.delete {
  color: #ff4d4f;
}
.menu-item.delete:hover {
  background-color: #fff1f0;
}
</style>
