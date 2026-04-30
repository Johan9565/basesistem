import fs from 'fs';
import path from 'path';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

/** Mismas rutas que `refresh: true` en laravel-vite-plugin, más layouts Vue compartidos. */
const laravelRefreshPaths = [
    'app/Livewire/**',
    'app/View/Components/**',
    'lang/**',
    'resources/lang/**',
    'resources/views/**',
    'resources/js/**',
    'routes/**',
    'resources/js/Layouts/**',
].filter((path) => fs.existsSync(path.replace(/\*\*$/, '')));

export default defineConfig({
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
    plugins: [
        tailwindcss(),
        laravel({
            input: 'resources/js/app.js',
            ssr: 'resources/js/ssr.js',
            refresh: [{ paths: laravelRefreshPaths }],
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
