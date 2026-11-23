import { useEffect, useState } from 'react';
import { adminUsersApi, PERMISSION_OPTIONS } from '../../api/admin';
import Can from '../../components/Can';
import { useAuth } from '../../store/auth';

const empty = { name: '', email: '', password: '', designation: '', permissions: [] };

export default function AdminsPage() {
  const { isSuperAdmin } = useAuth();
  const [items, setItems] = useState([]);
  const [form, setForm] = useState(empty);
  const [editingId, setEditingId] = useState(null);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const load = async () => { const { data } = await adminUsersApi.list(); setItems(data.data || []); };
  useEffect(() => { load(); }, []);

  const togglePerm = (key) => setForm(f => ({ ...f, permissions: f.permissions.includes(key) ? f.permissions.filter(p => p !== key) : [...f.permissions, key] }));

  const save = async (e) => {
    e.preventDefault(); setError(''); setLoading(true);
    try {
      if (editingId) await adminUsersApi.updatePermissions(editingId, { permissions: form.permissions, designation: form.designation });
      else await adminUsersApi.create(form);
      setForm(empty); setEditingId(null); await load();
    } catch (err) { setError(err.response?.data?.message || 'Save failed'); }
    finally { setLoading(false); }
  };

  const edit = (u) => { setEditingId(u.id); setForm({ name: u.name || '', email: u.email || '', password: '', designation: u.designation || '', permissions: u.permissions?.filter(p=>p!=='*') || [] }); };

  return (
    <div className="space-y-6">
      <div><h1 className="text-2xl font-semibold">Admins</h1><p className="text-sm text-slate-500">Manage admin users and granular permissions.</p></div>
      <Can superOnly>
        <form onSubmit={save} className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 space-y-4">
          {error && <div className="text-sm text-red-600">{error}</div>}
          <div className="grid md:grid-cols-4 gap-4">
            {['name','email','password','designation'].map(f => (
              <label key={f} className="text-sm block"><div className="mb-1 text-slate-600 dark:text-slate-300 capitalize">{f}</div>
                <input type={f==='password'?'password':'text'} disabled={!!editingId && f!=='designation'} value={form[f]} onChange={e=>setForm({...form,[f]:e.target.value})} className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent text-slate-900 dark:text-slate-100 disabled:opacity-50" />
              </label>
            ))}
          </div>
          <div>
            <div className="text-sm font-medium mb-2">Permissions</div>
            <div className="grid md:grid-cols-4 gap-2">
              {PERMISSION_OPTIONS.map(key => (
                <label key={key} className="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                  <input type="checkbox" checked={form.permissions.includes(key)} onChange={()=>togglePerm(key)} />
                  <span>{key}</span>
                </label>
              ))}
            </div>
          </div>
          <div className="flex gap-2">
            <button disabled={loading} className="px-4 py-2 rounded-lg bg-red-600 text-white text-sm">{editingId ? 'Update Permissions' : 'Add Admin User'}</button>
            {editingId && <button type="button" onClick={()=>{setEditingId(null);setForm(empty);}} className="px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-700 text-sm">Cancel</button>}
          </div>
        </form>
      </Can>
      <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-auto">
        <table className="w-full text-sm"><thead className="bg-slate-50 dark:bg-slate-800/50"><tr><th className="text-left p-3">Name</th><th className="text-left p-3">Email</th><th className="text-left p-3">Designation</th><th className="text-left p-3">Role</th><th className="p-3"></th></tr></thead>
          <tbody>{items.map(u => (
            <tr key={u.id} className="border-t border-slate-100 dark:border-slate-800">
              <td className="p-3">{u.name}</td><td className="p-3">{u.email}</td><td className="p-3">{u.designation || '-'}</td><td className="p-3">{u.role}</td>
              <td className="p-3 text-right">{isSuperAdmin && u.role !== 'super_admin' && <button onClick={()=>edit(u)} className="text-red-600">Edit permissions</button>}</td>
            </tr>
          ))}</tbody>
        </table>
      </div>
    </div>
  );
}
