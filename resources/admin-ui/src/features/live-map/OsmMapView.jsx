import { Fragment, useMemo } from 'react';
import L from 'leaflet';
import { Circle, MapContainer, Marker, Popup, TileLayer } from 'react-leaflet';
import { DEFAULT_CENTER, DEFAULT_ZOOM } from '../../services/googleMaps';

function escapeHtml(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function createPinIcon(item) {
  const name = escapeHtml(item.name || 'U');
  const initial = escapeHtml((item.name || 'U').slice(0, 1).toUpperCase());
  const avatar = item.avatar_path
    ? `<img src="${escapeHtml(item.avatar_path)}" alt="${name}" class="ft-pin-avatar-img" />`
    : `<span>${initial}</span>`;

  return L.divIcon({
    className: 'ft-avatar-pin',
    html: `<div class="ft-pin-halo"></div><div class="ft-pin-avatar">${avatar}</div>`,
    iconSize: [40, 40],
    iconAnchor: [20, 20],
  });
}

export default function OsmMapView({ items }) {
  const icons = useMemo(() => {
    const map = {};
    items.forEach(item => {
      map[item.employee_id] = createPinIcon({
        ...item,
        name: `${item.name || 'User'}${item.accuracy ? ` - ${Math.round(item.accuracy)}m accuracy` : ''}`,
      });
    });
    return map;
  }, [items]);

  return (
    <MapContainer
      center={DEFAULT_CENTER}
      zoom={DEFAULT_ZOOM}
      scrollWheelZoom
      className="h-full w-full"
      preferCanvas
    >
      <TileLayer
        attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
      />
      {items.map(item => (
        <Fragment key={item.employee_id}>
          {item.accuracy ? (
            <Circle
              center={[Number(item.latitude), Number(item.longitude)]}
              radius={Number(item.accuracy)}
              pathOptions={{ color: '#ED1D24', weight: 1, opacity: 0.35, fillColor: '#ED1D24', fillOpacity: 0.08 }}
            />
          ) : null}
          <Marker
            position={[Number(item.latitude), Number(item.longitude)]}
            icon={icons[item.employee_id]}
          >
            <Popup>
              <div className="text-sm">
                <div className="font-semibold">{item.name || `Employee ${item.employee_id}`}</div>
                {item.accuracy ? <div className="text-slate-500">Accuracy: {Math.round(item.accuracy)}m</div> : null}
                {item.recorded_at ? <div className="text-slate-500">Updated: {item.recorded_at}</div> : null}
              </div>
            </Popup>
          </Marker>
        </Fragment>
      ))}
    </MapContainer>
  );
}
