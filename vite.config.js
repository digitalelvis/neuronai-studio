import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'path';

export default defineConfig({
    plugins: [react()],
    build: {
        outDir: 'resources/js/canvas/dist',
        emptyOutDir: true,
        rollupOptions: {
            input: resolve(__dirname, 'resources/js/studio-canvas/main.jsx'),
            output: {
                entryFileNames: 'workflow-canvas.bundle.js',
                assetFileNames: 'workflow-canvas.[ext]',
            },
        },
    },
});
