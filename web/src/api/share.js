import api from './file';

export const createShare = (fileId, { password = null, expiredAt = null } = {}) => {
  return api.post('/shares/create', {
    file_id: fileId,
    password: password || null,
    expired_at: expiredAt || null,
  });
};

export const getShareDetail = (token, password = null) => {
  const params = {};
  if (password) params.password = password;
  return api.get(`/shares/${token}`, { params });
};

export const getShareFiles = (token, parentId, password = null) => {
  const params = {};
  if (parentId !== null && parentId !== undefined) params.parent_id = parentId;
  if (password) params.password = password;
  return api.get(`/shares/${token}/files`, { params });
};

export const buildShareDownloadUrl = (token, { fileId = null, password = null } = {}) => {
  const params = new URLSearchParams();
  if (fileId !== null && fileId !== undefined) params.set('file_id', String(fileId));
  if (password) params.set('password', password);
  const qs = params.toString();
  return `/api/shares/${token}/download${qs ? `?${qs}` : ''}`;
};

