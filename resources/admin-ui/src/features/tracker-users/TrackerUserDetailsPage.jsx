import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { trackerUsersApi } from '../../api/admin';

function formatDateTime(value) {
  if (!value) return '-';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString();
}

function formatDuration(seconds) {
  if (seconds === null || seconds === undefined) return '-';
  const total = Math.max(0, Number(seconds));
  const hours = Math.floor(total / 3600);
  const minutes = Math.floor((total % 3600) / 60);
  const secs = total % 60;
  if (hours > 0) return `${hours}h ${minutes}m ${secs}s`;
  if (minutes > 0) return `${minutes}m ${secs}s`;
  return `${secs}s`;
}

export default function TrackerUserDetailsPage() {
  const { id } = useParams();
  const [user, setUser] = useState(null);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let active = true;
    setLoading(true);
    setError('');
    trackerUsersApi.show(id)
      .then(({ data }) => {
        if (active) setUser(data.data || null);
      })
      .catch((err) => {
        if (active) setError(err.response?.data?.message || 'Failed to load tracker user details');
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => { active = false; };
  }, [id]);

  if (loading) return <div className="text-slate-500">Loading tracker user...</div>;
  if (error) return <div className="text-red-600">{error}</div>;
  if (!user) return <div className="text-slate-500">Tracker user not found.</div>;

  const employee = user.employee || {};
  const audit = Array.isArray(user.extra_duty_audit) ? user.extra_duty_audit : [];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold">Tracker User Details</h1>
          <p className="text-sm text-slate-500">Review user information and forgotten extra-duty sessions.</p>
        </div>
        <Link to="/tracker-users" className="text-sm text-red-600 hover:underline">Back to tracker users</Link>
      </div>

      <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5">
        <h2 className="text-lg font-medium mb-4">Summary</h2>
        <dl className="grid md:grid-cols-3 gap-4 text-sm">
          <div><dt className="text-slate-500">Name</dt><dd className="font-medium">{user.name || '-'}</dd></div>
          <div><dt className="text-slate-500">Email</dt><dd className="font-medium">{user.email || '-'}</dd></div>
          <div><dt className="text-slate-500">Employee code</dt><dd className="font-medium">{employee.employee_code || '-'}</dd></div>
          <div><dt className="text-slate-500">Designation</dt><dd className="font-medium">{user.designation || employee.designation || '-'}</dd></div>
          <div><dt className="text-slate-500">Duty schedule</dt><dd className="font-medium">{(employee.duty_start_time || '').slice(0, 5) || '-'} - {(employee.duty_end_time || '').slice(0, 5) || '-'}</dd></div>
          <div><dt className="text-slate-500">Status</dt><dd className="font-medium capitalize">{user.status || '-'}</dd></div>
        </dl>
      </div>

      <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-auto">
        <div className="p-5 border-b border-slate-200 dark:border-slate-800">
          <h2 className="text-lg font-medium">Forgotten Extra-Duty Audit</h2>
          <p className="text-sm text-slate-500">Uncounted extra-duty sessions auto-stopped at midnight. These are visible for audit and excluded from employee-counted totals.</p>
        </div>
        <table className="w-full text-sm">
          <thead className="bg-slate-50 dark:bg-slate-800/50 text-slate-600 dark:text-slate-300">
            <tr>
              <th className="text-left p-3">Session</th>
              <th className="text-left p-3">Started</th>
              <th className="text-left p-3">Ended</th>
              <th className="text-left p-3">Stop reason</th>
              <th className="text-left p-3">Duration</th>
              <th className="text-left p-3">Counted</th>
              <th className="text-left p-3">Locations</th>
            </tr>
          </thead>
          <tbody>
            {audit.length === 0 && (
              <tr><td className="p-3 text-slate-500" colSpan={7}>No forgotten extra-duty sessions found.</td></tr>
            )}
            {audit.map((session) => (
              <tr key={session.session_uuid} className="border-t border-slate-100 dark:border-slate-800 align-top">
                <td className="p-3 font-mono text-xs">{session.session_uuid}</td>
                <td className="p-3">{formatDateTime(session.started_at)}</td>
                <td className="p-3">{formatDateTime(session.ended_at)}</td>
                <td className="p-3">{session.stop_reason || '-'}</td>
                <td className="p-3">{formatDuration(session.duration_seconds)}</td>
                <td className="p-3">{session.counted ? 'Yes' : 'No'}</td>
                <td className="p-3">
                  {(session.locations || []).length === 0 ? (
                    <span className="text-slate-500">No locations</span>
                  ) : (
                    <div className="space-y-1">
                      {(session.locations || []).map((location, index) => (
                        <div key={location.id ?? `${session.session_uuid}-${index}`} className="text-xs">
                          <span className="text-slate-600 dark:text-slate-300">{formatDateTime(location.recorded_at)}</span>{' '}
                          <a
                            href={`https://www.google.com/maps?q=${location.latitude},${location.longitude}`}
                            target="_blank"
                            rel="noreferrer"
                            className="text-red-600 hover:underline"
                          >
                            Map
                          </a>
                        </div>
                      ))}
                    </div>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
