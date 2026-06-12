import { defineConfig, createLogger } from 'vite';
import laravel from 'laravel-vite-plugin';
import statamic from '@statamic/cms/vite-plugin';
import tailwindcss from '@tailwindcss/vite';

const logger = createLogger();
const warn = logger.warn.bind(logger);
logger.warn = (msg, options) => {
    if (msg.includes('resolve at build time') || msg.includes('.woff2')) return;
    warn(msg, options);
};

export default defineConfig({
    customLogger: logger,
    plugins: [
        tailwindcss(),
        statamic(),
        laravel({
            input: [
                'resources/css/cp.css',
                'resources/js/cp.js',
            ],
            hotFile: 'public/cp-hot',
            buildDirectory: 'vendor/app',
        }),
    ],
});
