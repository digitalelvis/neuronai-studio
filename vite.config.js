import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'path';

const target = process.env.BUILD_TARGET === 'chat' ? 'chat' : 'canvas';

const entries = {
    canvas: {
        input: resolve(__dirname, 'resources/js/studio-canvas/main.jsx'),
        fileName: 'workflow-canvas.bundle.js',
        cssName: 'workflow-canvas.css',
    },
    chat: {
        input: resolve(__dirname, 'resources/js/studio-chat/main.jsx'),
        fileName: 'studio-chat.bundle.js',
        cssName: 'studio-chat.css',
    },
};

const entry = entries[target];

export default defineConfig({
    plugins: [react()],
    build: {
        outDir: 'resources/js/dist',
        emptyOutDir: target === 'canvas',
        rollupOptions: {
            input: entry.input,
            output: {
                format: 'iife',
                name: target === 'chat' ? 'NeuronAIStudioChat' : 'NeuronAIStudioCanvas',
                entryFileNames: entry.fileName,
                assetFileNames: (assetInfo) => {
                    if (assetInfo.name?.endsWith('.css')) {
                        return entry.cssName;
                    }

                    return 'assets/[name][extname]';
                },
                inlineDynamicImports: true,
            },
        },
    },
});
