import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { AuthProvider, useAuth } from './store/auth';
import LoginPage from './features/auth/LoginPage';
import AdminLayout from './layouts/AdminLayout';
import DashboardPage from './features/dashboard/DashboardPage';
import LiveMapPage from './features/live-map/LiveMapPage';
import TrackerUsersPage from './features/tracker-users/TrackerUsersPage';
import AdminsPage from './features/admins/AdminsPage';
import SettingsPage from './features/settings/SettingsPage';
import Can from './components/Can';

function Protected({ children }) {
  const { user, loading } = useAuth();
  if (loading) return <div className="min-h-screen flex items-center justify-center text-slate-500">Loading...</div>;
  if (!user) return <Navigate to="/login" replace />;
  return children;
}

function Guarded({ permission, children }) {
  const { hasPermission } = useAuth();
  if (!hasPermission(permission)) return <div className="text-slate-500">You do not have access to this section.</div>;
  return children;
}

function RoutesApp() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route path="/" element={<Protected><AdminLayout /></Protected>}>
        <Route index element={<Guarded permission="dashboard.view"><DashboardPage /></Guarded>} />
        <Route path="live-map" element={<Guarded permission="live_map.view"><LiveMapPage /></Guarded>} />
        <Route path="tracker-users" element={<Guarded permission="tracker_users.view"><TrackerUsersPage /></Guarded>} />
        <Route path="admins" element={<Guarded permission="admins.view"><AdminsPage /></Guarded>} />
        <Route path="settings" element={<Guarded permission="settings.view"><SettingsPage /></Guarded>} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}

export default function App() {
  return (
    <AuthProvider>
      <BrowserRouter basename="/admin">
        <RoutesApp />
      </BrowserRouter>
    </AuthProvider>
  );
}
