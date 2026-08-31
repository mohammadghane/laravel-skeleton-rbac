import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    css: {
        // آدرس فونت‌ها (/vendor/fonts/...) مطلق‌اند و مستقیماً از public سرو
        // می‌شوند؛ Vite نباید آن‌ها را resolve یا کپی کند.
        preprocessorOptions: {},
    },
    build: {
        rollupOptions: {
            output: {
                // نام‌های قابل پیش‌بینی، تا در صورت نبود @vite هم بتوان مستقیم
                // به فایل لینک داد.
                assetFileNames: 'assets/[name]-[hash][extname]',
            },
        },
    },
});
