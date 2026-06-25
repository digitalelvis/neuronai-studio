import { resolve } from 'path';
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

const target = process.env.BUILD_TARGET ?? 'canvas';

const entries = {
    css: {
        input: resolve(__dirname, 'resources/css/studio-ui-entry.js'),
        fileName: 'studio-ui.js',
        cssName: 'studio-ui.css',
        format: 'iife',
        name: 'StudioUI',
    },
    canvas: {
        input: resolve(__dirname, 'resources/js/studio-canvas/main.jsx'),
        fileName: 'workflow-canvas.bundle.js',
        cssName: 'workflow-canvas.css',
        format: 'iife',
        name: 'NeuronAIStudioCanvas',
    },
    chat: {
        input: resolve(__dirname, 'resources/js/studio-chat/main.jsx'),
        fileName: 'studio-chat.bundle.js',
        cssName: 'studio-chat.css',
        format: 'iife',
        name: 'NeuronAIStudioChat',
    },
    forms: {
        input: resolve(__dirname, 'resources/js/studio-forms/main.jsx'),
        fileName: 'studio-forms.bundle.js',
        cssName: 'studio-forms.css',
        format: 'iife',
        name: 'NeuronAIStudioForms',
    },
};

const entry = entries[target] ?? entries.canvas;

export default defineConfig({
    plugins: [react(), tailwindcss()],
    resolve: {
        alias: {
            '@': resolve(__dirname, 'resources/js'),
        },
    },
    build: {
        outDir: target === 'css' ? 'resources/css' : 'resources/js/dist',
        emptyOutDir: false,
        cssCodeSplit: false,
        rollupOptions: {
            input: entry.input,
            output: {
                format: entry.format,
                ...(entry.name ? { name: entry.name } : {}),
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
