import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../../store/auth';

export default function LoginPage() {
  const { login } = useAuth();
  const nav = useNavigate();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const submit = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);
    try {
      await login(email, password);
      nav('/');
    } catch (err) {
      setError(err.response?.data?.message || 'Login failed');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-slate-100 flex items-center justify-center p-6 dark:bg-slate-950">
      <form onSubmit={submit} className="w-full max-w-md bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800 p-8">
        <div className="mb-6">
          <div className="text-sm font-medium text-red-600">PRAN RFL</div>
          <h1 className="text-2xl font-semibold text-slate-900 dark:text-white mt-1">Field Tracker Admin</h1>
          <p className="text-sm text-slate-500 mt-1">Sign in to manage tracking operations.</p>
        </div>
        {error && <div className="mb-4 text-sm text-red-600 bg-red-50 border border-red-100 rounded-lg px-3 py-2">{error}</div>}
        <label className="block text-sm mb-1 text-slate-700 dark:text-slate-300">Email</label>
        <input className="w-full mb-4 px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950" value={email} onChange={e=>setEmail(e.target.value)} />
        <label className="block text-sm mb-1 text-slate-700 dark:text-slate-300">Password</label>
        <input type="password" className="w-full mb-6 px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950" value={password} onChange={e=>setPassword(e.target.value)} />
        <button disabled={loading} className="w-full py-2.5 rounded-lg bg-red-600 hover:bg-red-700 text-white font-medium disabled:opacity-60">{loading ? 'Signing in...' : 'Sign in'}</button>
      </form>
    </div>
  );
}
