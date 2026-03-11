import { ref, computed } from 'vue';
import { message, Modal } from 'ant-design-vue';
import api, { 
  getFiles, 
  getFileDetail,
  getFileDownloadUrl,
  createFolder, 
  deleteFiles, 
  renameFile, 
  uploadFile, 
  moveFile,
  updateAccess
} from '../api/file';
import { createShare } from '../api/share';
import MarkdownIt from 'markdown-it';

/**
 * 文件浏览器核心逻辑 Hook（前端业务代码）。
 *
 * 主要职责：
 * - 列表/详情/下载/预览（含私有 Key 解锁）
 * - 管理操作：上传、重命名、删除、移动、访问设置、创建分享
 * - 面包屑导航、README 渲染、目录树懒加载
 */
export function useFileExplorer(options = {}) {
  // isAdmin=true 时表示管理模式（拥有写操作按钮与访问设置能力）
  const isAdmin = !!options?.isAdmin;

  // Markdown 渲染器：用于在目录页展示 README.md
  const md = new MarkdownIt({ html: true, linkify: true, typographer: true });

  // --- 核心状态 ---
  const loading = ref(false);
  const files = ref([]); // 文件列表数据
  const breadcrumbs = ref([
    { id: null, name: '全部文件' } // 面包屑初始状态
  ]);
  const readmeContent = ref(''); // 当前目录下的 README 内容

  // --- 访问控制（公开/私有） ---
  // 缓存“某个节点解锁成功后的 Key”（避免每次都弹窗）
  const passwordById = ref({});

  // 解锁（输入 Key）弹窗
  const showUnlockModal = ref(false);
  const unlockPasswordInput = ref('');
  const unlockTarget = ref(null);
  const unlockAction = ref(''); // enter | download | preview

  // 管理员：访问设置弹窗
  const showAccessModal = ref(false);
  const accessTarget = ref(null);
  const accessIsPublic = ref(true);
  const accessInitialIsPublic = ref(true);
  const accessPasswordInput = ref('');
  const accessSaving = ref(false);

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
  // 预览使用“短期签名 URL”（后端返回），避免把 Key 放进 URL
  const previewFile = ref(null);
  const previewUrl = ref('');
  let previewUrlSeq = 0;

  // --- 计算属性 ---
  // 当前所在的父文件夹 ID
  const currentParentId = computed(() => {
    const last = breadcrumbs.value[breadcrumbs.value.length - 1];
    return last.id;
  });

  const activePathPassword = computed(() => {
    // 从面包屑末尾往前找：一旦某层目录已解锁，子层默认可复用同一 Key
    for (let i = breadcrumbs.value.length - 1; i >= 0; i--) {
      const password = breadcrumbs.value[i]?.password;
      if (password) return password;
    }
    return null;
  });

  // 加载 README 内容
  const loadReadme = async (id, password = null) => {
    readmeContent.value = '';

    try {
      const headers = {};
      if (password) headers['X-Access-Key'] = password;

      // README 走下载接口获取原始文本；这里关闭 axios 的 JSON 解析
      const content = await api.get(`/files/${id}/download`, {
        headers,
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

  // 应用后端返回的列表数据，并在存在 README.md 时自动渲染
  const applyFileList = async (list, password = null) => {
    files.value = Array.isArray(list) ? list : [];

    // 仅对“公开可读”的 README 才展示（私有 README 需要先解锁）
    const readme = files.value.find(
      (file) => !file.is_folder && !file.is_protected && (file.name || '').toLowerCase() === 'readme.md'
    );
    if (readme) {
      await loadReadme(readme.id, password);
    }
  };

  // --- 核心方法：获取文件列表 ---
  const fetchFiles = async (parentId = null, { password } = {}) => {
    loading.value = true;
    readmeContent.value = ''; // 清空上一个目录的 README

    // 当前请求使用的 Key：优先使用显式传入，其次使用面包屑中缓存的“路径 Key”
    const effectivePassword = password ?? (isAdmin ? null : activePathPassword.value);

    try {
      const res = await getFiles(parentId, effectivePassword);

      if (res.code === 20000) {
        await applyFileList(res.data.list, effectivePassword);
        return;
      }

      message.error(res.message || '加载失败');
    } catch (err) {
      console.error(err);
      message.error('网络请求失败');
    } finally {
      loading.value = false;
    }
  };

  // --- 预览操作 ---
  const openPreview = (record, password = null) => {
    previewFile.value = record;
    previewUrl.value = '';

    if (!record) return;

    // 序号用于处理并发请求：只接受“最后一次打开预览”的结果
    const seq = ++previewUrlSeq;
    const effectivePassword = password ?? getEffectivePasswordFor(record);

    void (async () => {
      try {
        // 预览同样走两段式下载：先拿 signed URL，再设置为 img src
        const res = await getFileDownloadUrl(record.id, effectivePassword);
        if (seq !== previewUrlSeq) return;

        if (res.code === 20000 && res.data?.url) {
          previewUrl.value = res.data.url;
          return;
        }

        message.error(res.message || '获取预览链接失败');
      } catch (err) {
        console.error(err);
        if (seq === previewUrlSeq) {
          message.error('获取预览链接失败');
        }
      }
    })();
  };

  const closePreview = () => {
    previewFile.value = null;
    previewUrl.value = '';
    previewUrlSeq++;
  };

  const getEffectivePasswordFor = (record) => {
    if (isAdmin) return null;

    // 优先使用当前路径已解锁的 Key
    const pathPassword = activePathPassword.value;
    if (pathPassword) return pathPassword;

    // 其次使用对单个节点缓存的 Key
    const cachedPassword = passwordById.value?.[record?.id];
    if (cachedPassword) return cachedPassword;

    return null;
  };

  const previewSrc = computed(() => previewUrl.value);

  const doDownload = async (record, password = null) => {
    const effectivePassword = password ?? getEffectivePasswordFor(record);

    try {
      // 先请求后端生成短期 signed URL（避免把 Key 放到下载 URL）
      const res = await getFileDownloadUrl(record.id, effectivePassword);

      if (res.code !== 20000 || !res.data?.url) {
        message.error(res.message || '获取下载链接失败');
        return;
      }

      // 使用 <a> 触发浏览器下载（对文件夹会下载 zip）
      const url = res.data.url;
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', record.is_folder ? `${record.name}.zip` : record.name);
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    } catch (err) {
      console.error(err);
      message.error('获取下载链接失败');
    }
  };

  const openUnlockModal = (record, action) => {
    unlockTarget.value = record;
    unlockAction.value = action;
    unlockPasswordInput.value = '';
    showUnlockModal.value = true;
  };

  const cancelUnlockModal = () => {
    showUnlockModal.value = false;
    unlockPasswordInput.value = '';
    unlockTarget.value = null;
    unlockAction.value = '';
  };

  const submitUnlockPassword = async () => {
    const record = unlockTarget.value;
    const action = unlockAction.value;
    const password = (unlockPasswordInput.value || '').toString().trim();

    if (!record || !action) return;

    if (!password) return;

    if (!/^\d{6}$/.test(password)) {
      message.warning('Key 必须为 6 位数字');
      return;
    }

    // action=enter：用于“进入私有文件夹”，需要请求子列表验证 Key
    if (action === 'enter') {
      loading.value = true;
      readmeContent.value = '';

      try {
        const res = await getFiles(record.id, password);
        if (res.code === 20000) {
          passwordById.value[record.id] = password;
          breadcrumbs.value.push({ id: record.id, name: record.name, password });
          cancelUnlockModal();
          await applyFileList(res.data.list, password);
          return;
        }

        message.error(res.message || '加载失败');
      } catch (err) {
        console.error(err);
        message.error('网络请求失败');
      } finally {
        loading.value = false;
      }

      return;
    }

    // action=download/preview：先用详情接口验证 Key，再触发下载/预览
    try {
      const res = await getFileDetail(record.id, password);
      if (res.code === 20000) {
        passwordById.value[record.id] = password;
        cancelUnlockModal();

        if (action === 'preview') {
          openPreview(record, password);
          return;
        }

        await doDownload(record, password);
        return;
      }

      message.error(res.message || '操作失败');
    } catch (err) {
      console.error(err);
      message.error('网络请求失败');
    }
  };

  // 管理员：打开“公开/私有 Key”设置弹窗
  const openAccessSettings = (record) => {
    if (!isAdmin) return;

    accessTarget.value = record;
    accessIsPublic.value = !!record.is_public;
    accessInitialIsPublic.value = !!record.is_public;
    accessPasswordInput.value = '';
    showAccessModal.value = true;
  };

  // 管理员：关闭访问设置弹窗并重置状态
  const cancelAccessModal = () => {
    showAccessModal.value = false;
    accessTarget.value = null;
    accessPasswordInput.value = '';
    accessInitialIsPublic.value = true;
  };

  // 管理员：保存访问设置（公开/私有 + 可选 Key）
  const handleAccessSave = async () => {
    if (!isAdmin || !accessTarget.value) return;

    const password = (accessPasswordInput.value || '').toString().trim();

    if (!accessIsPublic.value) {
      if (password && !/^\d{6}$/.test(password)) {
        message.warning('Key 必须为 6 位数字');
        return;
      }

      if (!password && accessInitialIsPublic.value) {
        message.warning('请设置 6 位数字 Key');
        return;
      }
    }

    accessSaving.value = true;
    try {
      const payload = {
        isPublic: accessIsPublic.value,
      };

      if (!accessIsPublic.value && password) {
        payload.password = password;
      }

      const res = await updateAccess(accessTarget.value.id, payload);
      if (res.code === 20000) {
        message.success('访问权限已更新');
        cancelAccessModal();
        await fetchFiles(currentParentId.value);
        return;
      }

      message.error(res.message || '更新失败');
    } catch (err) {
      console.error(err);
      message.error('更新失败');
    } finally {
      accessSaving.value = false;
    }
  };

  // 进入文件夹：若为私有则尝试使用缓存 Key，否则弹出解锁弹窗
  const enterFolder = async (record) => {
    if (!record?.is_folder) return;

    if (isAdmin || activePathPassword.value) {
      // 管理员/已解锁路径：直接进入
      breadcrumbs.value.push({ id: record.id, name: record.name });
      await fetchFiles(record.id);
      return;
    }

    if (!record.is_protected) {
      // 公开文件夹：直接进入
      breadcrumbs.value.push({ id: record.id, name: record.name });
      await fetchFiles(record.id);
      return;
    }

    // 私有文件夹：优先尝试用缓存 Key 自动进入
    const cachedPassword = passwordById.value?.[record.id];
    if (cachedPassword) {
      loading.value = true;
      readmeContent.value = '';

      try {
        const res = await getFiles(record.id, cachedPassword);
        if (res.code === 20000) {
          breadcrumbs.value.push({ id: record.id, name: record.name, password: cachedPassword });
          await applyFileList(res.data.list, cachedPassword);
          return;
        }
      } catch (err) {
        console.error(err);
      } finally {
        loading.value = false;
      }
    }

    openUnlockModal(record, 'enter');
  };

  const openImagePreview = async (record) => {
    const pathPassword = activePathPassword.value;

    if (isAdmin || pathPassword || !record.is_protected) {
      openPreview(record, pathPassword);
      return;
    }

    const cachedPassword = passwordById.value?.[record.id];
    if (cachedPassword) {
      try {
        const res = await getFileDetail(record.id, cachedPassword);
        if (res.code === 20000) {
          openPreview(record, cachedPassword);
          return;
        }
      } catch (err) {
        console.error(err);
      }
    }

    openUnlockModal(record, 'preview');
  };

  const handleItemClick = async (record) => {
    if (!record) return;

    if (record.is_folder) {
      await enterFolder(record);
      return;
    }

    if (isImage(record)) {
      await openImagePreview(record);
      return;
    }

    await downloadFile(record);
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
    if (password && !/^\d{6}$/.test(password)) {
      message.warning('提取码必须为 6 位数字');
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

  const downloadFile = async (record) => {
    if (!record) return;

    if (isAdmin) {
      await doDownload(record);
      return;
    }

    const pathPassword = activePathPassword.value;
    if (pathPassword) {
      await doDownload(record, pathPassword);
      return;
    }

    if (!record.is_protected) {
      await doDownload(record);
      return;
    }

    const cachedPassword = passwordById.value?.[record.id];
    if (cachedPassword) {
      try {
        const res = await getFileDetail(record.id, cachedPassword);
        if (res.code === 20000) {
          await doDownload(record, cachedPassword);
          return;
        }
      } catch (err) {
        console.error(err);
      }
    }

    openUnlockModal(record, 'download');
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
    previewSrc,

    showUnlockModal,
    unlockPasswordInput,
    submitUnlockPassword,
    cancelUnlockModal,

    showAccessModal,
    accessTarget,
    accessIsPublic,
    accessPasswordInput,
    accessSaving,
    openAccessSettings,
    handleAccessSave,
    cancelAccessModal,

    handleItemClick,
    
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
