import api, { buildApiUrl } from './file';

// 分享相关 API（前端业务代码）

// 创建分享（管理接口）：可选 6 位数字提取码、可选过期时间
export const createShare = (fileId, { password = null, expiredAt = null } = {}) => {
  return api.post('/shares/create', {
    file_id: fileId,
    password: password || null,
    expired_at: expiredAt || null,
  });
};

// 获取分享详情：提取码优先用 Header（避免落入 URL）
export const getShareDetail = (token, password = null) => {
  const headers = {};
  if (password) headers['X-Share-Password'] = password;

  return api.get(`/shares/${token}`, { headers });
};

// 获取分享文件夹列表：支持 parent_id 导航
export const getShareFiles = (token, parentId, password = null) => {
  const params = {};
  if (parentId !== null && parentId !== undefined) params.parent_id = parentId;
  const headers = {};
  if (password) headers['X-Share-Password'] = password;

  return api.get(`/shares/${token}/files`, { params, headers });
};

// 构建“可直接跳转/下载”的分享下载链接
// 注意：浏览器直接下载无法携带自定义 Header，因此这里仍可能拼接 ?password=
export const buildShareDownloadUrl = (token, { fileId = null, password = null } = {}) => {
  const params = {};
  if (fileId !== null && fileId !== undefined) params.file_id = String(fileId);
  if (password) params.password = password;

  return buildApiUrl(`/shares/${token}/download`, params);
};
