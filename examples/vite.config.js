import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        tailwindcss(),
    ],
    publicDir: false,
    build: {
        outDir: 'public/build',
        manifest: true,
        rollupOptions: {
            input: {
                app: 'resources/js/app.js',
                'traffic-control': 'resources/js/pages/traffic-control.js',
                'global-lock': 'resources/js/pages/global-lock.js',
                'lottery-queue': 'resources/js/pages/lottery-queue.js',
                'lottery-queue-success': 'resources/js/pages/lottery-queue-success.js',
            },
        },
    },
    server: {
        port: 5173,
        cors: true,
    },
});
