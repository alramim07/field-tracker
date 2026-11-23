const STORAGE_PREFIX = 'field-tracker:';

export function getMapProvider() {
  return localStorage.getItem(`${STORAGE_PREFIX}map_provider`) || import.meta.env.VITE_MAP_PROVIDER || 'osm';
}

export function setMapProvider(provider) {
  localStorage.setItem(`${STORAGE_PREFIX}map_provider`, provider === 'google' ? 'google' : 'osm');
}

export function getGoogleMapsApiKey() {
  return localStorage.getItem(`${STORAGE_PREFIX}google_maps_api_key`) || import.meta.env.VITE_GOOGLE_MAPS_API_KEY || '';
}

export function setGoogleMapsApiKey(key) {
  if (key) localStorage.setItem(`${STORAGE_PREFIX}google_maps_api_key`, key);
  else localStorage.removeItem(`${STORAGE_PREFIX}google_maps_api_key`);
}

export function shouldUseGoogleMaps() {
  return getMapProvider() === 'google' && Boolean(getGoogleMapsApiKey());
}
