import api, { buildApiUrl } from './file';

export const createShare = (fileId, { password = null, expiredAt = null } = {}) => {
  return api.post('/shares/create', {
    file_id: fileId,
    password: password || null,
    expired_at: expiredAt || null,
  });
};

export const getShareDetail = (token, password = null) => {
  const headers = {};
  if (password) headers['X-Share-Password'] = password;

  return api.get(`/shares/${token}`, { headers });
};

export const getShareFiles = (token, parentId, password = null) => {
  const params = {};
  if (parentId !== null && parentId !== undefined) params.parent_id = parentId;
  const headers = {};
  if (password) headers['X-Share-Password'] = password;

  return api.get(`/shares/${token}/files`, { params, headers });
};

export const buildShareDownloadUrl = (token, { fileId = null, password = null } = {}) => {
  const params = {};
  if (fileId !== null && fileId !== undefined) params.file_id = String(fileId);
  if (password) params.password = password;

  return buildApiUrl(`/shares/${token}/download`, params);
};
