// Builds the embedded admin's React app (resources/app) into public/spa.
// The built files are committed, like the storefront runtime, so deploys don't need Node.
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  root: 'resources/app',
  base: '/spa/',
  plugins: [react()],
  build: {
    outDir: '../../public/spa',
    emptyOutDir: true,
    sourcemap: false,
    rollupOptions: {
      input: 'resources/app/main.jsx',
      output: {
        entryFileNames: 'app.js',
        chunkFileNames: 'chunks/[name]-[hash].js',
        assetFileNames: 'app.[ext]',
      },
    },
  },
});
