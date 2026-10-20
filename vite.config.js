import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/pages/admin.css',
                'resources/js/pages/admin.js',
                'resources/css/pages/auth-login.css',
                'resources/js/pages/auth-login.js',
                'resources/css/pages/auth-register.css',
                'resources/js/pages/auth-register.js',
                'resources/css/pages/auto-records.css',
                'resources/js/pages/auto-records.js',
                'resources/css/pages/automatic-count.css',
                'resources/js/pages/automatic-count.js',
                'resources/css/pages/cameras.css',
                'resources/js/pages/cameras.js',
                'resources/css/pages/dashboard.css',
                'resources/js/pages/dashboard.js',
                'resources/css/pages/live-traffic.css',
                'resources/js/pages/live-traffic.js',
                'resources/css/pages/manual-count.css',
                'resources/js/pages/manual-count.js',
                'resources/css/pages/manual-records.css',
                'resources/js/pages/manual-records.js',
                'resources/css/pages/predictions.css',
                'resources/js/pages/predictions.js',
                'resources/css/pages/reports.css',
                'resources/js/pages/reports.js',
                'resources/css/pages/schedules.css',
                'resources/js/pages/schedules.js',
                'resources/css/pages/welcome.css',
            ],
            refresh: true,
        }),
    ],
});
