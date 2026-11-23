import { Fragment } from 'react';
import { CircleF, GoogleMap, OverlayViewF, useJsApiLoader } from '@react-google-maps/api';
import { DEFAULT_CENTER, DEFAULT_ZOOM, MAP_OPTIONS } from '../../services/googleMaps';
import { getGoogleMapsApiKey } from '../../services/mapConfig';

function AvatarMarker({ item }) {
  return (
    <div style={{ transform: 'translate(-50%, -50%)' }} className="relative">
      <div className="absolute inset-0 rounded-full bg-red-500/20 animate-ping" style={{ width: 42, height: 42, left: -3, top: -3 }} />
      <div className="w-9 h-9 rounded-full border-2 border-white shadow bg-red-600 text-white flex items-center justify-center text-xs font-semibold overflow-hidden">
        {item.avatar_path ? <img src={item.avatar_path} alt={item.name} className="w-full h-full object-cover" /> : (item.name || 'U').slice(0, 1)}
      </div>
    </div>
  );
}

export default function GoogleMapView({ items }) {
  const { isLoaded, loadError } = useJsApiLoader({ googleMapsApiKey: getGoogleMapsApiKey() });

  if (loadError) return <div className="h-full flex items-center justify-center text-sm text-red-600">Google Maps failed to load. Check the API key.</div>;
  if (!isLoaded) return <div className="h-full flex items-center justify-center text-sm text-slate-500">Loading Google Maps...</div>;

  return (
    <GoogleMap mapContainerStyle={{ width: '100%', height: '100%' }} center={DEFAULT_CENTER} zoom={DEFAULT_ZOOM} options={MAP_OPTIONS}>
      {items.map(item => (
        <Fragment key={item.employee_id}>
          {item.accuracy ? (
            <CircleF
              center={{ lat: Number(item.latitude), lng: Number(item.longitude) }}
              radius={Number(item.accuracy)}
              options={{ strokeColor: '#ED1D24', strokeOpacity: 0.35, strokeWeight: 1, fillColor: '#ED1D24', fillOpacity: 0.08 }}
            />
          ) : null}
          <OverlayViewF position={{ lat: Number(item.latitude), lng: Number(item.longitude) }} mapPaneName="overlayMouseTarget">
            <AvatarMarker item={item} />
          </OverlayViewF>
        </Fragment>
      ))}
    </GoogleMap>
  );
}
