import path from 'node:path';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { defineConfig, type Plugin } from 'vite';
import { bunny } from 'laravel-vite-plugin/fonts';
import laravel from 'laravel-vite-plugin';
import { nativephpHotFile, nativephpMobile } from './vendor/nativephp/mobile/resources/js/vite-plugin.js';

function nativeAssetPlatform(): Plugin {
    const platform = process.argv.includes('--mode=ios') ? 'ios'
        : process.argv.includes('--mode=android') ? 'android' : 'web';

    return {
        name: 'buff-native-asset-platform',
        generateBundle() {
            this.emitFile({
                type: 'asset',
                fileName: 'native-platform',
                source: `${platform}\n`,
            });
        },
    };
}

export default defineConfig({
    resolve: {
        alias: {
            '@': path.resolve(import.meta.dirname, 'resources/js'),
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
                    optimizedFallbacks: false,
                }),
                bunny('Fraunces', {
                    weights: [900],
                    styles: ['italic'],
                    optimizedFallbacks: false,
                }),
            ],
        }),
        vue(),
        tailwindcss(),
        nativephpMobile(),
        nativeAssetPlatform(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
