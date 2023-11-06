import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [react()],
  build: {
    outDir: 'dist',
    assetsDir: 'assets',
    manifest: true,
    modulePreload: {
      polyfill: false,
    },
    esbuild: {
      jsxInject: `import React from 'react';`,
    },
    rollupOptions: {
      output: {
        entryFileNames: `assets/[name].js`,
        chunkFileNames: `assets/[name].js`,
        assetFileNames: `assets/[name].[ext]`,
      },
    },
  },
  server: {
    cors: true,
    strictPort: true,
    port: 3001,
    hmr: {
      port: 3001,
      host: 'localhost',
      ws: true,
    },
  },
});
