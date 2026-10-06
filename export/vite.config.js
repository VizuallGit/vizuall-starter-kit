import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    build: {
        emptyOutDir: false,
    },
    plugins: [
        laravel({
            input: ['resources/css/site.css', 'resources/js/contrast.js', 'resources/js/alpine.js', 'resources/js/blur-load.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: [
                '**/storage/**',
                '**/users/**',
                '**/content/**',
                '**/bootstrap/cache/**',
            ],
        },
    },
});
