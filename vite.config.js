import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

const lanHost = process.env.VITE_LAN_HOST ?? '192.168.254.100';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        origin: `http://${lanHost}:5173`,
        hmr: {
            host: lanHost,
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
