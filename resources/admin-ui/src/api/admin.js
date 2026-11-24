import client from './client';

export const trackerUsersApi = {
  list: (params) => client.get('/admin/tracker-users', { params }),
  show: (id) => client.get(`/admin/tracker-users/${id}`),
  create: (data) => client.post('/admin/tracker-users', data),
  update: (id, data) => client.put(`/admin/tracker-users/${id}`, data),
  remove: (id) => client.delete(`/admin/tracker-users/${id}`),
};

export const adminUsersApi = {
  list: (params) => client.get('/admin/admin-users', { params }),
  create: (data) => client.post('/admin/admin-users', data),
  updatePermissions: (id, data) => client.put(`/admin/admin-users/${id}/permissions`, data),
};

export const dashboardApi = {
  summary: () => client.get('/admin/dashboard/summary'),
};

export const liveLocationsApi = {
  list: () => client.get('/admin/live-locations', { params: { per_page: 200 } }),
};

export const PERMISSION_OPTIONS = [
  'dashboard.view','dashboard.create','dashboard.edit','dashboard.delete',
  'live_map.view','live_map.create','live_map.edit','live_map.delete',
  'tracker_users.view','tracker_users.create','tracker_users.edit','tracker_users.delete',
  'admins.view','admins.create','admins.edit','admins.delete',
  'settings.view','settings.create','settings.edit','settings.delete',
];
