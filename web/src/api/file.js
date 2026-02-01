import axios from 'axios';

const api = axios.create({
  baseURL: '/api',
  timeout: 10000,
  validateStatus: function (status) {
    return status >= 200 && status < 500; // 只要不是 500 错误，都视为请求完成，交由业务逻辑判断
  }
});

// 响应拦截器：处理统一的错误格式
api.interceptors.response.use(
  response => {
    // 假设后端返回格式 { status: 200, data: {...}, ... }
    // 实际业务数据在 response.data.data
    return response.data;
  },
  error => {
    console.error('API Error:', error);
    return Promise.reject(error);
  }
);

export const getFiles = (parentId = null) => {
  return api.get('/files', {
    params: { parent_id: parentId }
  });
};

export const getFileDetail = (id) => {
    return api.get(`/files/${id}`);
};

// --- 管理接口 (需要 Key) ---

// 设置 API Key 到请求头
export const setApiKey = (key) => {
  if (key) {
    api.defaults.headers.common['X-Api-Key'] = key;
    localStorage.setItem('api_key', key); // 持久化
  } else {
    delete api.defaults.headers.common['X-Api-Key'];
    localStorage.removeItem('api_key');
  }
};

// 初始化时尝试读取本地 Key
const storedKey = localStorage.getItem('api_key');
if (storedKey) {
  api.defaults.headers.common['X-Api-Key'] = storedKey;
}

export const createFolder = (name, parentId = null) => {
  return api.post('/folders', { name, parent_id: parentId });
};

export const renameFile = (id, name) => {
  return api.post('/files/rename', { id, name });
};

export const deleteFiles = (ids) => {
  return api.delete('/files/delete', { data: { ids } });
};

export const moveFile = (id, targetParentId) => {
  return api.post('/files/move', { id, parent_id: targetParentId });
};

export const uploadFile = (formData, onUploadProgress) => {
  return api.post('/files/upload', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
    onUploadProgress
  });
};


export default api;
