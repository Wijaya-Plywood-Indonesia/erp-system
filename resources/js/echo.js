import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

console.log('Echo.js sedang dimuat...');
window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
   cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
//     wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
//     wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    // forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    forceTLS: false,
    // enabledTransports: ['ws', 'wss'],
});
console.log('Echo berhasil dipasang ke window!');