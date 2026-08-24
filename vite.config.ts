import path from 'node:path';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { defineConfig } from 'vite';
import { bunny } from 'laravel-vite-plugin/fonts';
import laravel from 'laravel-vite-plugin';
import { nativephpHotFile, nativephpMobile } from './vendor/nativephp/mobile/resources/js/vite-plugin.js';

export default defineConfig({
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            hotFile: nativephpHotFile(),
            fonts: [
                bunny('IBM Plex Sans', {
                    weights: [400, 500, 600, 700],
                }),
                bunny('Fraunces', {
                    weights: [900],
                    styles: ['italic'],
                }),
            ],
        }),
        vue(),
        tailwindcss(),
        nativephpMobile(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
