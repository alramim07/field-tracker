import { createContext, useContext, useEffect, useMemo, useState } from 'react';
import client from '../api/client';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [permissions, setPermissions] = useState([]);
  const [loading, setLoading] = useState(true);

  const isSuperAdmin = user?.role === 'super_admin';
  const hasPermission = (key) => isSuperAdmin || permissions.includes('*') || permissions.includes(key);

  const refresh = async () => {
    try {
      const { data } = await client.get('/admin/auth/me');
      setUser(data.data.user);
      setPermissions(data.data.permissions || []);
    } catch {
      setUser(null);
      setPermissions([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { refresh(); }, []);

  const login = async (email, password) => {
    await client.get('/sanctum/csrf-cookie', { baseURL: '/' });
    const { data } = await client.post('/admin/auth/login', { email, password });
    setUser(data.data.user);
    setPermissions(data.data.permissions || []);
  };

  const logout = async () => {
    await client.post('/admin/auth/logout');
    setUser(null);
    setPermissions([]);
  };

  const value = useMemo(() => ({ user, permissions, loading, isSuperAdmin, hasPermission, login, logout, refresh }), [user, permissions, loading]);
  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  return useContext(AuthContext);
}
