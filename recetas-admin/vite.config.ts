import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

const apiOrigin = process.env.RECETAS_API_ORIGIN ?? 'http://recetas-api:8080';

export default defineConfig({
  base: '/admin/',
  plugins: [react()],
  server: {
    host: '0.0.0.0',
    proxy: {
      '/api': {
        target: apiOrigin,
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/api/, ''),
      },
      '/imagenes': {
        target: apiOrigin,
        changeOrigin: true,
      },
    },
  },
});
