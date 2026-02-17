import { ref, computed } from 'vue';
import { message, Modal } from 'ant-design-vue';
import api, { 
  getFiles, 
  createFolder, 
  deleteFiles, 
  renameFile, 
  uploadFile, 
  moveFile 
} from '../api/file';
import { createShare } from '../api/share';
import MarkdownIt from 'markdown-it';

/**
 * 文件浏览器核心逻辑 Hook
 * 包含：列表获取、增删改查操作、上传、面包屑管理
 */
export function useFileExplorer() {
  const md = new MarkdownIt({ html: true, linkify: true, typographer: true });

  // --- 核心状态 ---
  const loading = ref(false);
  const files = ref([]); // 文件列表数据
  const breadcrumbs = ref([
    { id: null, name: '全部文件' } // 面包屑初始状态
  ]);
  const readmeContent = ref(''); // 当前目录下的 README 内容

  // --- 弹窗控制状态 ---
  const showCreateFolderModal = ref(false);
  const newFolderName = ref('');
  
  const showRenameModal = ref(false);
  const renameTarget = ref(null); // 当前重命名的文件对象
  const renameInput = ref('');
  
  const showMoveModal = ref(false);
  const moveTargetId = ref([]); // 移动目标文件夹ID (Tree组件用数组)
  const movingFile = ref(null); // 当前移动的文件对象
  const treeData = ref([]); // 移动弹窗里的目录树数据
  const treeLoading = ref(false);

  // --- 分享状态 ---
  const showShareModal = ref(false);
  const shareTarget = ref(null);
  const sharePassword = ref('');
  const shareExpiredAt = ref(null);
  const shareCreating = ref(false);
  const shareResult = ref(null);

  // --- 上传状态 ---
  const uploading = ref(false);
  const activeUploads = ref(0);

  // --- 预览状态 ---
  const previewFile = ref(null);

  // --- 计算属性 ---
  // 当前所在的父文件夹 ID
  const currentParentId = computed(() => {
    const last = breadcrumbs.value[breadcrumbs.value.length - 1];
    return last.id;
  });

  // --- 核心方法：获取文件列表 ---
  const fetchFiles = async (parentId = null) => {
    loading.value = true;
    readmeContent.value = ''; // 清空上一个目录的 README
    try {
      console.log('Fetching files for parentId:', parentId); // Debug Log
      const res = await getFiles(parentId);
      console.log('Fetch response:', res); // Debug Log

      if (res.code === 20000) {
        files.value = res.data.list;
        // 自动查找并加载 README.md
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

  // 加载 README 内容
  const loadReadme = async (id) => {
    try {
      const content = await api.get(`/files/${id}/download`, {
        responseType: 'text',
        transformResponse: [data => data] // 防止 axios 自动解析 JSON
      });
      if (typeof content === 'string') {
          readmeContent.value = md.render(content);
      }
    } catch (err) {
      console.error('README 加载失败', err);
    }
  };

  // --- 预览操作 ---
  const openPreview = (record) => {
    previewFile.value = record;
  };

  const closePreview = () => {
    previewFile.value = null;
  };

  // --- 业务操作：分享 ---
  const openShare = (record) => {
    shareTarget.value = record;
    sharePassword.value = '';
    shareExpiredAt.value = null;
    shareResult.value = null;
    showShareModal.value = true;
  };

  const buildShareLink = (share) => {
    const token = share?.token ?? share?.share_token;
    if (token) {
      return new URL(`/s/${token}`, window.location.origin).toString();
    }

    const link = share?.link;
    if (!link) return null;
    try {
      return new URL(link, window.location.origin).toString();
    } catch (e) {
      return link;
    }
  };

  const copyShareLink = async () => {
    const link = buildShareLink(shareResult.value);
    if (!link) return;

    try {
      await navigator.clipboard.writeText(link);
      message.success('链接已复制');
    } catch (err) {
      // fallback
      try {
        const textarea = document.createElement('textarea');
        textarea.value = link;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        message.success('链接已复制');
      } catch (e) {
        message.error('复制失败，请手动复制');
      }
    }
  };

  const handleShareOk = async () => {
    if (shareResult.value) {
      showShareModal.value = false;
      return;
    }

    if (!shareTarget.value) return;

    const password = sharePassword.value?.trim() || null;
    if (password && (password.length < 4 || password.length > 6)) {
      message.warning('提取码长度应为 4-6 位');
      return;
    }

    const expiredAt = shareExpiredAt.value?.format
      ? shareExpiredAt.value.format('YYYY-MM-DD HH:mm:ss')
      : null;

    shareCreating.value = true;
    try {
      const res = await createShare(shareTarget.value.id, { password, expiredAt });
      if (res.code === 20000) {
        shareResult.value = {
          ...res.data,
          link: buildShareLink(res.data) || res.data?.link,
        };
        message.success('分享创建成功');
      } else {
        message.error(res.message || '创建失败');
      }
    } catch (err) {
      console.error(err);
      message.error('创建失败');
    } finally {
      shareCreating.value = false;
    }
  };

  // --- 业务操作：新建文件夹 ---
  const openCreateFolder = () => {
    newFolderName.value = '';
    showCreateFolderModal.value = true;
  };

  const handleCreateFolder = async () => {
    if (!newFolderName.value) return;
    try {
      const res = await createFolder(newFolderName.value, currentParentId.value);
      
      // 兼容后端不同的响应格式 (裸对象 vs 标准结构)
      const isSuccess = 
        (res.code >= 20000 && res.code < 30000) || 
        (res.status >= 200 && res.status < 300) ||
        (res.status === 'success') ||
        (res.code === 201) || // Response::created
        (res && res.id) ||    // 直接返回模型对象
        (res.data && !res.error);

      if (isSuccess) {
        message.success('创建成功');
        showCreateFolderModal.value = false;
        fetchFiles(currentParentId.value); // 刷新列表
      } else {
        message.error(res.message || '创建失败');
      }
    } catch (err) {
      console.error(err);
      message.error('创建失败');
    }
  };

  // --- 业务操作：重命名 ---
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

  // --- 业务操作：删除 ---
  const handleDelete = (record) => {
    Modal.confirm({
      title: '确认删除',
      content: `确定要删除 "${record.name}" 吗？此操作不可恢复。`,
      okText: '删除',
      okType: 'danger',
      cancelText: '取消',
      onOk: async () => {
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
      }
    });
  };

  // --- 业务操作：移动 ---
  const openMove = async (record) => {
    movingFile.value = record;
    showMoveModal.value = true;
    moveTargetId.value = [];
    treeData.value = [];
    treeLoading.value = true;
    
    // 加载根目录
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

  // 转换文件列表为 Tree 节点
  const transformToTreeNodes = (list) => {
    return list.filter(item => item.is_folder).map(item => ({
        title: item.name, key: item.id, id: item.id, isLeaf: false
    }));
  };

  // 懒加载子目录
  const onLoadTreeData = async (treeNode) => {
    if (treeNode.children && treeNode.children.length > 0) return;
    const parentId = treeNode.id;
    try {
      const res = await getFiles(parentId);
      if (res.code === 20000) {
        treeNode.dataRef.children = transformToTreeNodes(res.data.list);
        treeData.value = [...treeData.value]; // 触发响应式更新
      }
    } catch (err) {}
  };

  const handleMove = async () => {
    if (!movingFile.value) return;
    
    let targetId = null;
    if (Array.isArray(moveTargetId.value) && moveTargetId.value.length > 0) {
      targetId = moveTargetId.value[0];
    }
    if (targetId === 'root') targetId = null; // 根目录
    
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

  // --- 业务操作：上传 ---
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
      // 宽松的成功判断
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
      // 当所有并发上传完成后，刷新列表
      if (activeUploads.value === 0) {
        uploading.value = false;
        setTimeout(() => fetchFiles(currentParentId.value), 500);
      }
    }
  };

  // --- 导航操作：点击面包屑 ---
  const handleBreadcrumbClick = (item, index) => {
    if (previewFile.value) closePreview(); // 如果在预览模式，先退出
    if (index === breadcrumbs.value.length - 1) return;
    breadcrumbs.value = breadcrumbs.value.slice(0, index + 1);
    fetchFiles(item.id);
  };

  // --- 工具方法 ---
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
    const url = `/api/files/${record.id}/download`;
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', record.is_folder ? `${record.name}.zip` : record.name);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  // 返回所有需要暴露给组件的属性和方法
  return {
    loading,
    files,
    breadcrumbs,
    readmeContent,
    currentParentId,
    
    showCreateFolderModal,
    newFolderName,
    
    showRenameModal,
    renameTarget,
    renameInput,
    
    showMoveModal,
    moveTargetId,
    treeData,
    treeLoading,

    showShareModal,
    shareTarget,
    sharePassword,
    shareExpiredAt,
    shareCreating,
    shareResult,
    
    uploading,
    
    previewFile,
    openPreview,
    closePreview,
    
    fetchFiles,
    openCreateFolder,
    handleCreateFolder,
    openRename,
    handleRename,
    handleDelete,
    openMove,
    handleMove,
    onLoadTreeData,
    handleUpload,
    handleBreadcrumbClick,
    openShare,
    handleShareOk,
    copyShareLink,
     
    formatSize,
    formatDate,
    isImage,
    downloadFile
  };
}
