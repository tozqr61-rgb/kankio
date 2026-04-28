import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],

    build: {
        /* Terser: strip console.* and debugger in production builds */
        minify: 'terser',
        terserOptions: {
            compress: {
                drop_console:  true,
                drop_debugger: true,
                pure_funcs: ['console.log', 'console.warn', 'console.debug', 'console.info'],
            },
            mangle: { safari10: true }, /* Safari 10 compat for PWA */
        },

        /* Separate node_modules from app code — better long-term cache hits */
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules')) return 'vendor';
                },
            },
        },

        cssCodeSplit:          true,   /* per-entry CSS chunks */
        chunkSizeWarningLimit: 500,    /* warn if chunk > 500 kB */
        sourcemap:             false,  /* no source maps in production */
    },

    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
