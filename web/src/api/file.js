import axios from 'axios';

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
      // ignore invalid URL
    }
  }

  return trimmed;
};

export const API_BASE_URL = normalizeApiBaseUrl(import.meta.env.VITE_API_BASE_URL);

export const buildApiUrl = (path, params = {}) => {
  const normalizedPath = (path ?? '').toString();
  const apiPath = normalizedPath.startsWith('/') ? normalizedPath : `/${normalizedPath}`;

  const url = new URL(`${API_BASE_URL}${apiPath}`, window.location.origin);

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
  const headers = {};
  if (password) headers['X-Access-Key'] = password;

  return api.get('/files', { params, headers });
};

export const getFileDetail = (id, password = null) => {
  const headers = {};
  if (password) headers['X-Access-Key'] = password;

  return api.get(`/files/${id}`, { headers });
};

export const getFileDownloadUrl = (id, password = null) => {
  const payload = {};
  if (password) payload.password = password;

  return api.post(`/files/${id}/download-url`, payload);
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
