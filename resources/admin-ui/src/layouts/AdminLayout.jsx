import { NavLink, Outlet } from 'react-router-dom';
import { useEffect, useState } from 'react';
import { useAuth } from '../store/auth';
import Can from '../components/Can';

const nav = [
  { to: '/', label: 'Dashboard', perm: 'dashboard.view', icon: '▦' },
  { to: '/live-map', label: 'Live Map', perm: 'live_map.view', icon: '◎' },
  { to: '/tracker-users', label: 'Tracker Users', perm: 'tracker_users.view', icon: '♙' },
  { to: '/admins', label: 'Admins', perm: 'admins.view', icon: '♟' },
  { to: '/settings', label: 'Settings', perm: 'settings.view', icon: '⚙' },
];

export default function AdminLayout() {
  const { user, logout } = useAuth();
  const [dark, setDark] = useState(() => localStorage.getItem('theme') === 'dark');
  const [collapsed, setCollapsed] = useState(() => localStorage.getItem('sidebar_collapsed') === '1');
  const [now, setNow] = useState(new Date());

  useEffect(() => { document.documentElement.classList.toggle('dark', dark); localStorage.setItem('theme', dark ? 'dark' : 'light'); }, [dark]);
  useEffect(() => { localStorage.setItem('sidebar_collapsed', collapsed ? '1' : '0'); }, [collapsed]);
  useEffect(() => { const t = setInterval(() => setNow(new Date()), 1000); return () => clearInterval(t); }, []);

  return (
    <div className="min-h-screen bg-slate-100 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
      <div className="flex min-h-screen">
        <aside className={`${collapsed ? 'w-20' : 'w-64'} bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 flex flex-col transition-all duration-200`}>
          <div className={`${collapsed ? 'px-3 justify-center' : 'px-5'} py-5 border-b border-slate-200 dark:border-slate-800 flex flex-col`}>
            <div className="text-lg font-semibold text-center">{collapsed ? 'FT' : 'Field Tracker'}</div>
            {!collapsed && <div className="text-xs text-slate-500 dark:text-slate-400 text-center">Admin Dashboard</div>}
          </div>

          <nav className="flex-1 p-3 space-y-1">
            {nav.map(item => (
              <Can key={item.to} permission={item.perm}>
                <NavLink
                  to={item.to}
                  end={item.to === '/'}
                  title={collapsed ? item.label : undefined}
                  className={({ isActive }) => `flex items-center gap-3 px-3 py-2 rounded-lg text-sm ${isActive ? 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'} ${collapsed ? 'justify-center' : ''}`}
                >
                  <span className="text-base leading-none">{item.icon}</span>
                  {!collapsed && <span>{item.label}</span>}
                </NavLink>
              </Can>
            ))}
          </nav>

          <div className={`p-4 border-t border-slate-200 dark:border-slate-800 ${collapsed ? 'text-center' : ''}`}>
            <div className={`flex items-center gap-3 ${collapsed ? 'justify-center' : ''}`}>
              <div className="w-9 h-9 rounded-full bg-red-100 text-red-700 dark:bg-red-950/60 dark:text-red-300 flex items-center justify-center font-semibold">{(user?.name || 'U').slice(0, 1)}</div>
              {!collapsed && (
                <div className="flex-1 min-w-0">
                  <div className="text-sm font-medium truncate">{user?.name}</div>
                  <div className="text-xs text-slate-500 truncate">{user?.designation || user?.role}</div>
                </div>
              )}
            </div>
            <button
              onClick={logout}
              title="Logout"
              className="mt-3 w-full text-sm px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800"
            >
              {collapsed ? '⇥' : 'Logout'}
            </button>
          </div>
        </aside>

        <div className="flex-1 flex flex-col min-w-0">
          <header className="h-16 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 px-4 sm:px-6 flex items-center justify-between gap-3">
            <div className="flex items-center gap-3 min-w-0">
              <button
                onClick={() => setCollapsed(c => !c)}
                className="px-3 py-2 text-lg leading-none rounded-lg border border-slate-300 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800"
                title="Toggle sidebar"
                aria-label="Toggle sidebar"
              >
                ☰
              </button>
              <div className="text-sm text-slate-500 dark:text-slate-400 truncate">{now.toLocaleDateString()} {now.toLocaleTimeString()}</div>
            </div>
            <div className="flex items-center gap-3">
              <button className="px-3 py-2 text-sm rounded-lg border border-slate-300 dark:border-slate-700" title="Notifications (placeholder)">🔔</button>
              <button onClick={() => setDark(d => !d)} className="px-3 py-2 text-sm rounded-lg border border-slate-300 dark:border-slate-700">{dark ? 'Light' : 'Dark'}</button>
            </div>
          </header>
          <main className="flex-1 p-4 sm:p-6 min-w-0"><Outlet /></main>
        </div>
      </div>
    </div>
  );
}
