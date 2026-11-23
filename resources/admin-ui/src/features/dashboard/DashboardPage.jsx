import { useEffect, useState } from 'react';
import { dashboardApi } from '../../api/admin';

const cards = [
  { key: 'active_users', label: 'Currently using app', tone: 'text-emerald-600' },
  { key: 'late_to_start', label: 'Late to start tracker', tone: 'text-amber-600' },
  { key: 'not_using_tracker', label: 'Not using during duty', tone: 'text-red-600' },
  { key: 'started_duty_inactive_now', label: 'Started duty, inactive now', tone: 'text-slate-700 dark:text-slate-200' },
];

export default function DashboardPage() {
  const [data, setData] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    dashboardApi.summary().then(r => setData(r.data.data)).catch(e => setError(e.response?.data?.message || 'Failed to load metrics'));
  }, []);

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold">Dashboard</h1>
        <p className="text-sm text-slate-500">Live operational overview for field tracking.</p>
      </div>
      {error && <div className="text-sm text-red-600">{error}</div>}
      <div className="grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
        {cards.map(c => (
          <div key={c.key} className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5">
            <div className="text-sm text-slate-500">{c.label}</div>
            <div className={`mt-2 text-3xl font-semibold ${c.tone}`}>{data ? data[c.key] : '—'}</div>
          </div>
        ))}
      </div>
      <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-auto">
        <div className="px-5 py-4 border-b border-slate-200 dark:border-slate-800 font-medium">Started at duty time but inactive now</div>
        <table className="w-full text-sm">
          <thead className="bg-slate-50 dark:bg-slate-800/50 text-slate-600 dark:text-slate-300"><tr><th className="text-left p-3">Employee</th><th className="text-left p-3">Duty window</th></tr></thead>
          <tbody>
            {(data?.started_duty_inactive_now_list || []).length === 0 ? (
              <tr><td className="p-4 text-slate-500" colSpan="2">No inactive users in this state.</td></tr>
            ) : data.started_duty_inactive_now_list.map(u => (
              <tr key={u.employee_id} className="border-t border-slate-100 dark:border-slate-800">
                <td className="p-3">{u.name || `Employee #${u.employee_id}`}</td>
                <td className="p-3">{String(u.duty_start_time).slice(0,5)} - {String(u.duty_end_time).slice(0,5)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
