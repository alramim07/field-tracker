export const GOOGLE_MAPS_API_KEY = import.meta.env.VITE_GOOGLE_MAPS_API_KEY || '';
export const DEFAULT_CENTER = { lat: 23.8103, lng: 90.4125 };
export const DEFAULT_ZOOM = 7;
export const MAP_OPTIONS = {
  mapTypeControl: false,
  streetViewControl: false,
  fullscreenControl: true,
  clickableIcons: false,
};
