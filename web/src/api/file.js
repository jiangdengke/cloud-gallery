import axios from 'axios';

const api = axios.create({
  baseURL: '/api',
  timeout: 10000,
  validateStatus: (status) => status >= 200 && status < 500,
});

api.interceptors.response.use(
  (response) => response.data,
  (error) => {
    console.error('API Error:', error);
    return Promise.reject(error);
  }
);

export const getFiles = (parentId = null, password = null) => {
  const params = { parent_id: parentId };
  if (password) params.password = password;

  return api.get('/files', { params });
};

export const getFileDetail = (id, password = null) => {
  const params = {};
  if (password) params.password = password;

  return api.get(`/files/${id}`, { params });
};

export const setApiKey = (key) => {
  const normalizedKey = (key ?? '').toString().trim();

  if (normalizedKey) {
    api.defaults.headers.common['X-Api-Key'] = normalizedKey;
    localStorage.setItem('api_key', normalizedKey);
    return;
  }

  delete api.defaults.headers.common['X-Api-Key'];
  localStorage.removeItem('api_key');
};

const storedKey = localStorage.getItem('api_key');
if (storedKey && storedKey.trim()) {
  api.defaults.headers.common['X-Api-Key'] = storedKey.trim();
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

export const uploadFile = (formData, onUploadProgress) => {
  return api.post('/files/upload', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
    onUploadProgress,
  });
};

export default api;
