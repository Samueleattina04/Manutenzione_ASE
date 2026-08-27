// Thin API client around fetch. All requests are same-origin with cookie auth.

async function handle(res) {
  const ct = res.headers.get('content-type') || '';
  const data = ct.includes('application/json') ? await res.json().catch(() => ({})) : {};
  if (!res.ok) {
    const err = new Error(data.error || `Errore ${res.status}`);
    err.status = res.status;
    throw err;
  }
  return data;
}

function jsonReq(method) {
  return (url, body) =>
    fetch(url, {
      method,
      headers: { 'Content-Type': 'application/json' },
      body: body ? JSON.stringify(body) : undefined,
    }).then(handle);
}

const get = (url) => fetch(url).then(handle);
const post = jsonReq('POST');
const patch = jsonReq('PATCH');
const del = (url) => fetch(url, { method: 'DELETE' }).then(handle);
const postForm = (url, formData) => fetch(url, { method: 'POST', body: formData }).then(handle);

export const api = {
  // auth
  me: () => get('/api/auth/me'),
  login: (username, password) => post('/api/auth/login', { username, password }),
  logout: () => post('/api/auth/logout'),
  changePassword: (currentPassword, newPassword) =>
    post('/api/auth/change-password', { currentPassword, newPassword }),

  // meta
  meta: () => get('/api/meta'),

  // requests
  listRequests: (params = {}) => {
    const qs = new URLSearchParams(
      Object.entries(params).filter(([, v]) => v != null && v !== '')
    ).toString();
    return get('/api/requests' + (qs ? `?${qs}` : ''));
  },
  stats: () => get('/api/requests/stats'),
  getRequest: (id) => get(`/api/requests/${id}`),
  createRequest: (formData) => postForm('/api/requests', formData),
  addUpdate: (id, formData) => postForm(`/api/requests/${id}/updates`, formData),
  addAttachments: (id, formData) => postForm(`/api/requests/${id}/attachments`, formData),
  deleteAttachment: (id) => del(`/api/attachments/${id}`),

  // users (admin)
  listUsers: () => get('/api/users'),
  createUser: (payload) => post('/api/users', payload),
  updateUser: (id, payload) => patch(`/api/users/${id}`, payload),
};
