import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                // Common files
                'resources/css/common/datatables.css',
                // Page-specific files
                'resources/js/pages/students-index.js',
            ],
            refresh: true,
        }),
    ],
});
