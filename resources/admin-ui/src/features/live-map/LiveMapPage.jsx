import { useEffect, useMemo, useRef, useState } from 'react';
import { liveLocationsApi } from '../../api/admin';
import { createEcho } from '../../services/realtime';
import { shouldUseGoogleMaps } from '../../services/mapConfig';
import GoogleMapView from './GoogleMapView';
import OsmMapView from './OsmMapView';

function lerp(a, b, t) { return a + (b - a) * t; }

function formatDuration(seconds) {
  if (seconds === null || seconds === undefined) return '--:--:--';
  const total = Math.max(0, Math.floor(Number(seconds)));
  const hours = Math.floor(total / 3600);
  const minutes = Math.floor((total % 3600) / 60);
  const secs = total % 60;
  return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
}

function formatClock(value) {
  if (!value) return '--:--:--';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '--:--:--';
  return date.toLocaleTimeString();
}

export default function LiveMapPage() {
  const [items, setItems] = useState({});
  const [error, setError] = useState('');
  const [tick, setTick] = useState(0);
  const animRef = useRef({});
  const itemsRef = useRef({});
  const tickRef = useRef(0);
  const trackerBaseRef = useRef({});
  itemsRef.current = items;

  const useGoogle = shouldUseGoogleMaps();

  const setTrackerBase = (id, seconds) => {
    trackerBaseRef.current[id] = { seconds: Number(seconds) || 0, tick: tickRef.current };
  };

  useEffect(() => {
    const timer = setInterval(() => {
      tickRef.current += 1;
      setTick(tickRef.current);
    }, 1000);
    return () => clearInterval(timer);
  }, []);

  const upsert = (incoming) => {
    const id = incoming.employee_id;
    if (!id) return;
    const prev = itemsRef.current[id];
    const target = {
      ...prev,
      ...incoming,
      latitude: Number(incoming.latitude),
      longitude: Number(incoming.longitude),
      accuracy: incoming.accuracy != null ? Number(incoming.accuracy) : null,
    };
    if (!prev || target.tracker_seconds !== prev.tracker_seconds) {
      setTrackerBase(id, target.tracker_seconds);
    }
    if (!prev) {
      setItems(s => ({ ...s, [id]: target }));
      return;
    }
    const start = { lat: Number(prev.latitude), lng: Number(prev.longitude) };
    const end = { lat: target.latitude, lng: target.longitude };
    const startTs = performance.now();
    const duration = 900;
    cancelAnimationFrame(animRef.current[id]);
    const step = (ts) => {
      const t = Math.min(1, (ts - startTs) / duration);
      const lat = lerp(start.lat, end.lat, t);
      const lng = lerp(start.lng, end.lng, t);
      setItems(s => ({ ...s, [id]: { ...s[id], ...target, latitude: lat, longitude: lng } }));
      if (t < 1) animRef.current[id] = requestAnimationFrame(step);
    };
    animRef.current[id] = requestAnimationFrame(step);
  };

  useEffect(() => {
    liveLocationsApi.list()
      .then(({ data }) => {
        const map = {};
        (data.data || []).forEach(i => {
          map[i.employee_id] = { ...i, latitude: Number(i.latitude), longitude: Number(i.longitude) };
          setTrackerBase(i.employee_id, i.tracker_seconds);
        });
        setItems(map);
      })
      .catch(e => setError(e.response?.data?.message || 'Failed to load live locations'));
  }, []);

  useEffect(() => {
    const echo = createEcho();
    if (!echo) return undefined;
    const channel = echo.private('admin.live-locations');
    channel.listen('.live-location.updated', payload => upsert(payload));
    return () => {
      try {
        channel.stopListening('.live-location.updated');
        echo.leave('admin.live-locations');
        echo.disconnect();
      } catch { /* ignore cleanup errors */ }
    };
  }, []);

  useEffect(() => () => {
    Object.values(animRef.current).forEach(id => cancelAnimationFrame(id));
  }, []);

  const list = useMemo(() => Object.values(items).sort((a, b) => String(b.recorded_at || '').localeCompare(String(a.recorded_at || ''))), [items]);

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-2xl font-semibold">Live Map</h1>
        <p className="text-sm text-slate-500">Real-time field employee locations.</p>
      </div>

      {error && <div className="text-sm text-red-600">{error}</div>}

      <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden" style={{ height: '70vh' }}>
        {useGoogle ? <GoogleMapView items={list} /> : <OsmMapView items={list} />}
      </div>

      <div className="flex items-center justify-between text-xs text-slate-500">
        <span>Showing {list.length} active location(s).</span>
        <span>Map: {useGoogle ? 'Google Maps' : 'OpenStreetMap'}</span>
      </div>

      <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden">
        <div className="px-5 py-4 border-b border-slate-200 dark:border-slate-800">
          <h2 className="text-base font-semibold">Live location details</h2>
          <p className="text-xs text-slate-500">Last location updates automatically with each new ping.</p>
        </div>
        <div className="overflow-x-auto">
          <table className="min-w-[880px] w-full text-sm">
            <thead className="bg-slate-50 dark:bg-slate-800/60 text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
              <tr>
                <th className="text-left px-5 py-3 font-medium">User / place</th>
                <th className="text-left px-5 py-3 font-medium">Current location</th>
                <th className="text-left px-5 py-3 font-medium">Last location</th>
                <th className="text-left px-5 py-3 font-medium">Tracker time</th>
                <th className="text-left px-5 py-3 font-medium">Offline during duty</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
              {list.length === 0 ? (
                <tr>
                  <td colSpan="5" className="px-5 py-8 text-center text-slate-500">No live locations yet.</td>
                </tr>
              ) : list.map(item => {
                const last = item.last_location || {};
                const trackerBase = trackerBaseRef.current[item.employee_id] || { seconds: Number(item.tracker_seconds) || 0, tick: 0 };
                const liveTrackerSeconds = item.is_tracking
                  ? trackerBase.seconds + Math.max(0, tick - trackerBase.tick)
                  : trackerBase.seconds;
                return (
                  <tr key={item.employee_id}>
                    <td className="px-5 py-3">
                      <div className="flex items-center gap-3">
                        <div className="w-10 h-10 rounded-full overflow-hidden bg-red-100 text-red-700 dark:bg-red-950/60 dark:text-red-300 flex items-center justify-center font-semibold shrink-0">
                          {item.avatar_path ? <img src={item.avatar_path} alt={item.name || 'User'} className="w-full h-full object-cover" /> : (item.name || 'U').slice(0, 1).toUpperCase()}
                        </div>
                        <div className="min-w-0">
                          <div className="font-medium truncate">{item.name || `Employee ${item.employee_id}`}</div>
                          <div className="text-xs text-slate-500 truncate">{item.designation || 'Field user'}</div>
                        </div>
                      </div>
                    </td>
                    <td className="px-5 py-3 max-w-[260px] truncate" title={item.place_name}>{item.place_name || `${Number(item.latitude).toFixed(4)}, ${Number(item.longitude).toFixed(4)}`}</td>
                    <td className="px-5 py-3 max-w-[260px]">
                      <div className="truncate" title={last.place_name || ''}>{last.place_name || 'No previous ping'}</div>
                      <div className="text-xs text-slate-500">{formatClock(last.recorded_at)}</div>
                    </td>
                    <td className="px-5 py-3 font-mono">{formatDuration(liveTrackerSeconds)}</td>
                    <td className="px-5 py-3 font-mono text-amber-700 dark:text-amber-400">{formatDuration(item.offline_seconds)}</td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
        <div className="px-5 py-3 text-xs text-slate-500 border-t border-slate-200 dark:border-slate-800">
          Place names © <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noreferrer" className="underline">OpenStreetMap contributors</a>
        </div>
      </div>
    </div>
  );
}
