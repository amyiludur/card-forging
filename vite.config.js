import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    // Bind the dev server to IPv4. Node resolves `localhost` to ::1 on Windows,
    // so Vite would listen on [::1] while `php artisan serve` listens on
    // 127.0.0.1 - a split stack that writes `http://[::1]:5173` into public/hot.
    server: {
        host: '127.0.0.1',
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
});
