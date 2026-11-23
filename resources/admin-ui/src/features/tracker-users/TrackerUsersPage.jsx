import { useEffect, useState } from 'react';
import { trackerUsersApi } from '../../api/admin';
import Can from '../../components/Can';

const empty = { name: '', email: '', password: '', employee_code: '', phone: '', designation: '', duty_start_time: '', duty_end_time: '', status: 'active' };

export default function TrackerUsersPage() {
  const [items, setItems] = useState([]);
  const [form, setForm] = useState(empty);
  const [editingId, setEditingId] = useState(null);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const load = async () => {
    const { data } = await trackerUsersApi.list();
    setItems(data.data || []);
  };
  useEffect(() => { load(); }, []);

  const save = async (e) => {
    e.preventDefault();
    setError(''); setLoading(true);
    try {
      const payload = { ...form };
      if (!payload.password) delete payload.password;
      if (editingId) await trackerUsersApi.update(editingId, payload);
      else await trackerUsersApi.create(payload);
      setForm(empty); setEditingId(null); await load();
    } catch (err) {
      setError(err.response?.data?.message || 'Save failed');
    } finally { setLoading(false); }
  };

  const edit = (u) => {
    setEditingId(u.id);
    setForm({
      name: u.name || '', email: u.email || '', password: '', employee_code: u.employee?.employee_code || '',
      phone: u.employee?.phone || '', designation: u.designation || u.employee?.designation || '',
      duty_start_time: (u.employee?.duty_start_time || '').slice(0,5), duty_end_time: (u.employee?.duty_end_time || '').slice(0,5),
      status: u.status || 'active',
    });
  };

  return (
    <div className="space-y-6">
      <div><h1 className="text-2xl font-semibold">Tracker Users</h1><p className="text-sm text-slate-500">Manage field employees and duty schedules.</p></div>
      <Can permission="tracker_users.create">
        <form onSubmit={save} className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 grid md:grid-cols-3 gap-4">
          {error && <div className="md:col-span-3 text-sm text-red-600">{error}</div>}
          {['name','email','password','employee_code','phone','designation'].map(f => (
            <label key={f} className="text-sm block">
              <div className="mb-1 text-slate-600 dark:text-slate-300 capitalize">{f.replace('_',' ')}</div>
              <input type={f==='password'?'password':'text'} value={form[f]} onChange={e=>setForm({...form,[f]:e.target.value})} className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent text-slate-900 dark:text-slate-100" />
            </label>
          ))}
          <label className="text-sm block"><div className="mb-1 text-slate-600 dark:text-slate-300">Duty start</div><input type="time" value={form.duty_start_time} onChange={e=>setForm({...form,duty_start_time:e.target.value})} className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent text-slate-900 dark:text-slate-100" /></label>
          <label className="text-sm block"><div className="mb-1 text-slate-600 dark:text-slate-300">Duty end</div><input type="time" value={form.duty_end_time} onChange={e=>setForm({...form,duty_end_time:e.target.value})} className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent text-slate-900 dark:text-slate-100" /></label>
          <div className="flex items-end gap-2">
            <button disabled={loading} className="px-4 py-2 rounded-lg bg-red-600 text-white text-sm">{editingId ? 'Update' : 'Create'}</button>
            {editingId && <button type="button" onClick={()=>{setEditingId(null);setForm(empty);}} className="px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-700 text-sm">Cancel</button>}
          </div>
        </form>
      </Can>
      <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-auto">
        <table className="w-full text-sm">
          <thead className="bg-slate-50 dark:bg-slate-800/50 text-slate-600 dark:text-slate-300"><tr><th className="text-left p-3">Name</th><th className="text-left p-3">Email</th><th className="text-left p-3">Employee Code</th><th className="text-left p-3">Duty</th><th className="p-3"></th></tr></thead>
          <tbody>
            {items.map(u => (
              <tr key={u.id} className="border-t border-slate-100 dark:border-slate-800">
                <td className="p-3">{u.name}</td><td className="p-3">{u.email}</td><td className="p-3">{u.employee?.employee_code}</td>
                <td className="p-3">{u.employee?.duty_start_time?.slice(0,5)} - {u.employee?.duty_end_time?.slice(0,5)}</td>
                <td className="p-3 text-right"><Can permission="tracker_users.edit"><button onClick={()=>edit(u)} className="text-red-600">Edit</button></Can></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
