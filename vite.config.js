import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/vita-assistant/public.js',
                'resources/js/vita-phone.js',
            ],
            refresh: true,
        }),
    ],
});
