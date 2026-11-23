import { useEffect, useMemo, useRef, useState } from 'react';
import { liveLocationsApi } from '../../api/admin';
import { createEcho } from '../../services/realtime';
import { shouldUseGoogleMaps } from '../../services/mapConfig';
import GoogleMapView from './GoogleMapView';
import OsmMapView from './OsmMapView';

function lerp(a, b, t) { return a + (b - a) * t; }

export default function LiveMapPage() {
  const [items, setItems] = useState({});
  const [error, setError] = useState('');
  const animRef = useRef({});
  const itemsRef = useRef({});
  itemsRef.current = items;

  const useGoogle = shouldUseGoogleMaps();

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

  const list = useMemo(() => Object.values(items), [items]);

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
    </div>
  );
}
