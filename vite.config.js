import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const devHost = env.VITE_DEV_HOST || '0.0.0.0';
    const hmrHost = env.VITE_HMR_HOST || '127.0.0.1';
    const devOrigin = env.VITE_DEV_ORIGIN || `http://${hmrHost}:5173`;
    const corsOrigin = env.VITE_CORS_ORIGIN || env.APP_URL || true;

    return {
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        host: devHost,
        port: 5173,
        origin: devOrigin,
        cors: { origin: corsOrigin },
        hmr: {
            host: hmrHost,
            port: 5173,
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    };
});
