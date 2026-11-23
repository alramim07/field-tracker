import { useEffect, useMemo, useRef, useState } from 'react';
import { CircleF, GoogleMap, OverlayViewF, useJsApiLoader } from '@react-google-maps/api';
import { liveLocationsApi } from '../../api/admin';
import { createEcho } from '../../services/realtime';
import { DEFAULT_CENTER, DEFAULT_ZOOM, GOOGLE_MAPS_API_KEY, MAP_OPTIONS } from '../../services/googleMaps';

function lerp(a, b, t) { return a + (b - a) * t; }

function AvatarMarker({ item }) {
  return (
    <div style={{ transform: 'translate(-50%, -50%)' }} className="relative">
      <div className="absolute inset-0 rounded-full bg-red-500/20 animate-ping" style={{ width: 42, height: 42, left: -3, top: -3 }} />
      <div className="w-9 h-9 rounded-full border-2 border-white shadow bg-red-600 text-white flex items-center justify-center text-xs font-semibold overflow-hidden">
        {item.avatar_path ? <img src={item.avatar_path} alt={item.name} className="w-full h-full object-cover" /> : (item.name || 'U').slice(0,1)}
      </div>
    </div>
  );
}

export default function LiveMapPage() {
  const [items, setItems] = useState({});
  const [error, setError] = useState('');
  const animRef = useRef({});
  const itemsRef = useRef({});
  itemsRef.current = items;
  const { isLoaded, loadError } = useJsApiLoader({ googleMapsApiKey: GOOGLE_MAPS_API_KEY });

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
        (data.data || []).forEach(i => { map[i.employee_id] = { ...i, latitude: Number(i.latitude), longitude: Number(i.longitude) }; });
        setItems(map);
      })
      .catch(e => setError(e.response?.data?.message || 'Failed to load live locations'));
  }, []);

  useEffect(() => {
    const echo = createEcho();
    if (!echo) return undefined;
    const channel = echo.private('admin.live-locations');
    channel.listen('.live-location.updated', (payload) => upsert(payload));
    return () => {
      try { channel.stopListening('.live-location.updated'); echo.leave('admin.live-locations'); echo.disconnect(); } catch {}
    };
  }, []);

  const list = useMemo(() => Object.values(items), [items]);

  return (
    <div className="space-y-4">
      <div><h1 className="text-2xl font-semibold">Live Map</h1><p className="text-sm text-slate-500">Real-time field employee locations.</p></div>
      {error && <div className="text-sm text-red-600">{error}</div>}
      {!GOOGLE_MAPS_API_KEY && <div className="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-3">Set <code>VITE_GOOGLE_MAPS_API_KEY</code> in <code>resources/admin-ui/.env</code> to load Google Maps.</div>}
      {loadError && <div className="text-sm text-red-600">Failed to load Google Maps.</div>}
      <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden" style={{ height: '70vh' }}>
        {isLoaded && GOOGLE_MAPS_API_KEY ? (
          <GoogleMap mapContainerStyle={{ width: '100%', height: '100%' }} center={DEFAULT_CENTER} zoom={DEFAULT_ZOOM} options={MAP_OPTIONS}>
            {list.map(item => (
              <div key={item.employee_id}>
                {item.accuracy ? <CircleF center={{ lat: Number(item.latitude), lng: Number(item.longitude) }} radius={Number(item.accuracy)} options={{ strokeColor: '#ED1D24', strokeOpacity: 0.35, strokeWeight: 1, fillColor: '#ED1D24', fillOpacity: 0.08 }} /> : null}
                <OverlayViewF position={{ lat: Number(item.latitude), lng: Number(item.longitude) }} mapPaneName="overlayMouseTarget">
                  <AvatarMarker item={item} />
                </OverlayViewF>
              </div>
            ))}
          </GoogleMap>
        ) : (
          <div className="h-full flex items-center justify-center text-slate-500 text-sm">Map will appear when Google Maps key is configured.</div>
        )}
      </div>
      <div className="text-xs text-slate-500">Showing {list.length} active location(s).</div>
    </div>
  );
}
