import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;

if (reverbKey) {
    window.Pusher = Pusher;

    const rawHost = import.meta.env.VITE_REVERB_HOST;
    const wsHost = (rawHost && rawHost !== 'localhost') ? rawHost : (typeof window !== 'undefined' ? window.location.hostname : 'localhost');
    const wsPort = import.meta.env.VITE_REVERB_PORT ?? 8080;
    const isHttps = (import.meta.env.VITE_REVERB_SCHEME ?? (typeof window !== 'undefined' && window.location.protocol === 'https:' ? 'https' : 'http')) === 'https';

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: wsHost,
        wsPort: wsPort,
        wssPort: wsPort,
        forceTLS: isHttps,
        enabledTransports: ['ws', 'wss'],
    });
}
