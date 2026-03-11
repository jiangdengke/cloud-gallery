import axios from 'axios';

// 后端 API 基础地址：
// - 默认同源 /api（适合“前后端同站点部署”）
// - 前后端分离部署时可在 web/.env 配置 VITE_API_BASE_URL 指向后端
const normalizeApiBaseUrl = (value) => {
  const raw = (value ?? '').toString().trim();
  if (!raw) return '/api';

  const trimmed = raw.replace(/\/+$/, '');

  if (/^https?:\/\//i.test(trimmed)) {
    try {
      const url = new URL(trimmed);
      const pathname = url.pathname.replace(/\/+$/, '');
      if (pathname === '' || pathname === '/') {
        url.pathname = '/api';
        return url.toString().replace(/\/+$/, '');
      }
    } catch (e) {
      // 非法 URL：直接走后续 fallback
    }
  }

  return trimmed;
};

// 供其它模块引用的 API base
export const API_BASE_URL = normalizeApiBaseUrl(import.meta.env.VITE_API_BASE_URL);

// 拼接 API URL（主要用于“构建可直接跳转的下载链接”）
export const buildApiUrl = (path, params = {}) => {
  const normalizedPath = (path ?? '').toString();
  const apiPath = normalizedPath.startsWith('/') ? normalizedPath : `/${normalizedPath}`;

  // 使用 window.location.origin 作为基准，兼容 API_BASE_URL 为相对路径/绝对路径
  const url = new URL(`${API_BASE_URL}${apiPath}`, window.location.origin);

  // 只拼接有值的 query 参数
  for (const [key, value] of Object.entries(params ?? {})) {
    if (value === null || value === undefined) continue;
    const stringValue = String(value);
    if (!stringValue) continue;
    url.searchParams.set(key, stringValue);
  }

  return url.toString();
};

const api = axios.create({
  baseURL: API_BASE_URL,
  timeout: 10000,
  // 将 4xx 也作为“正常响应”返回给业务层统一处理（避免落入 catch）
  validateStatus: (status) => status >= 200 && status < 500,
});

api.interceptors.response.use(
  (response) => response.data,
  (error) => {
    // 网络错误 / 超时 / CORS 等异常会走这里
    console.error('API Error:', error);
    return Promise.reject(error);
  }
);

// 获取文件列表：私有目录需要 Key（Header: X-Access-Key）
export const getFiles = (parentId = null, password = null) => {
  const params = { parent_id: parentId };
  const headers = {};
  if (password) headers['X-Access-Key'] = password;

  return api.get('/files', { params, headers });
};

// 获取文件详情：私有资源需要 Key
export const getFileDetail = (id, password = null) => {
  const headers = {};
  if (password) headers['X-Access-Key'] = password;

  return api.get(`/files/${id}`, { headers });
};

// 两段式下载：先拿短期签名 URL（避免把 Key 暴露在下载 URL 上）
export const getFileDownloadUrl = (id, password = null) => {
  const payload = {};
  if (password) payload.password = password;

  return api.post(`/files/${id}/download-url`, payload);
};

// 设置/清空管理员 API Key（写操作需要）
export const setApiKey = (key) => {
  const normalizedKey = (key ?? '').toString().trim();

  if (normalizedKey) {
    // 写入 axios 默认 header，并持久化到 localStorage
    api.defaults.headers.common['X-Api-Key'] = normalizedKey;
    localStorage.setItem('api_key', normalizedKey);
    return;
  }

  // 清空 header 与本地存储
  delete api.defaults.headers.common['X-Api-Key'];
  localStorage.removeItem('api_key');
};

// 启动时尝试恢复管理员 Key
const storedKey = localStorage.getItem('api_key');
if (storedKey && storedKey.trim()) {
  api.defaults.headers.common['X-Api-Key'] = storedKey.trim();
}

// 新建文件夹（管理接口）
export const createFolder = (name, parentId = null) => {
  return api.post('/folders', { name, parent_id: parentId });
};

// 重命名（管理接口）
export const renameFile = (id, name) => {
  return api.post('/files/rename', { id, name });
};

// 删除（管理接口）
export const deleteFiles = (ids) => {
  return api.delete('/files/delete', { data: { ids } });
};

// 移动（管理接口）
export const moveFile = (id, targetParentId) => {
  return api.post('/files/move', { id, parent_id: targetParentId });
};

// 访问控制设置（管理接口）：公开/私有 + 6 位数字 Key（可选）
export const updateAccess = (id, { isPublic, password } = {}) => {
  const payload = {
    id,
    is_public: isPublic,
  };

  if (password !== undefined) {
    payload.password = password;
  }

  return api.post('/files/access', payload);
};

// 上传文件（管理接口）
export const uploadFile = (formData, onUploadProgress) => {
  return api.post('/files/upload', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
    onUploadProgress,
  });
};

export default api;
