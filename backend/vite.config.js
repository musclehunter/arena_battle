import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
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
    server: {
        // コンテナ内では 0.0.0.0 で待ち受け、ホストブラウザからは localhost:5173 に見せる
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        origin: 'http://localhost:5173',
        // Laravel 本体 (http://localhost) から Vite (http://localhost:5173) への
        // クロスオリジン fetch を許可する
        cors: {
            origin: ['http://localhost', 'http://127.0.0.1'],
        },
        hmr: {
            host: 'localhost',
            protocol: 'ws',
        },
        watch: {
            // Windows + Docker 環境で変更検知が効かないことがあるので polling
            usePolling: true,
            interval: 500,
        },
    },
});
