import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import path from 'path';

export default defineConfig({
  plugins: [react(), tailwindcss()],
  base: '/build-admin/',
  build: {
    outDir: path.resolve(__dirname, '../../public/build-admin'),
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: path.resolve(__dirname, 'src/main.jsx'),
    },
  },
  server: {
    port: 5174,
    strictPort: true,
    origin: 'http://localhost:5174',
  },
});
