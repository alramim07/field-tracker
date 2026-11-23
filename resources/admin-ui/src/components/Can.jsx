import { useAuth } from '../store/auth';

export default function Can({ permission, superOnly = false, children }) {
  const { isSuperAdmin, hasPermission } = useAuth();
  if (superOnly) return isSuperAdmin ? children : null;
  if (!permission) return children;
  return hasPermission(permission) ? children : null;
}
