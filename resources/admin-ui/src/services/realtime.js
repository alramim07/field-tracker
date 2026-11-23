import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

export function createEcho() {
  const key = import.meta.env.VITE_REVERB_APP_KEY;
  const host = import.meta.env.VITE_REVERB_HOST;
  if (!key || !host) return null;

  return new Echo({
    broadcaster: 'reverb',
    key,
    wsHost: host,
    wsPort: Number(import.meta.env.VITE_REVERB_PORT || 80),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT || 443),
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: '/broadcasting/auth',
    withCredentials: true,
  });
}
